<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fiscal;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-2.md task 2.4: CRUD, live recalculation on every
 * update, and the validation rules task 2.6's issuance use case will
 * reuse (customer resolvable, at least one line, exemption reason on a
 * 0%-rate line, references for NC/ND, series matches document type).
 */
final class DraftsControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/companies/00000000-0000-7000-8000-000000000000/drafts');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreatingAnEmptyDraftHasNoCalculatedResult(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => 'FT',
            'payload' => [],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$created['id']}", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{document_type: string, calculated: mixed, series_id: mixed, customer_id: mixed} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('FT', $body['document_type']);
        self::assertNull($body['calculated']);
        self::assertNull($body['series_id']);
        self::assertNull($body['customer_id']);
    }

    public function testCreatingWithAnUnknownDocumentTypeIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => 'ZZ',
            'payload' => [],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testACalculableDraftGetsALiveCalculatedResultThatUpdatesOnEveryChange(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $draftId = $this->createDraft($client, $companyId, 'FT', [
            'pricing_mode' => 'net',
            'rounding_method' => 'per_line',
            'date' => '2026-01-01',
            'lines' => [
                ['quantity' => '1', 'unit_price' => '100.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
            ],
        ]);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$draftId}", server: self::HEADERS);
        /** @var array{calculated: array{net_total: string, tax_total: string, gross_total: string}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('100.00', $body['calculated']['net_total']);
        self::assertSame('23.00', $body['calculated']['tax_total']);
        self::assertSame('123.00', $body['calculated']['gross_total']);

        $client->request('PUT', "/api/v1/companies/{$companyId}/drafts/{$draftId}", server: self::HEADERS, content: json_encode([
            'payload' => [
                'pricing_mode' => 'net',
                'rounding_method' => 'per_line',
                'date' => '2026-01-01',
                'lines' => [
                    ['quantity' => '2', 'unit_price' => '100.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
                ],
            ],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$draftId}", server: self::HEADERS);
        /** @var array{calculated: array{net_total: string}} $updated */
        $updated = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('200.00', $updated['calculated']['net_total'], 'calculated must reflect the latest payload, live.');
    }

    public function testAnUncalculablePayloadStillSavesButLeavesCalculatedNull(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        // Missing unit_price: /calculate would 422 on this, but a draft
        // must still accept the save — it's mid-edit, not a document.
        $draftId = $this->createDraft($client, $companyId, 'FT', [
            'lines' => [['quantity' => '1', 'tax_region' => 'PT', 'tax_code' => 'NOR']],
        ]);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$draftId}", server: self::HEADERS);
        /** @var array{calculated: mixed, payload: array{lines: list<mixed>}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertNull($body['calculated']);
        self::assertCount(1, $body['payload']['lines'], 'The payload itself must still be saved as given.');
    }

    public function testDeletingADraftRemovesItForGood(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $draftId = $this->createDraft($client, $companyId, 'FT', []);

        $client->request('DELETE', "/api/v1/companies/{$companyId}/drafts/{$draftId}", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$draftId}", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testListingReturnsEveryDraftForTheCompany(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $this->createDraft($client, $companyId, 'FT', []);
        $this->createDraft($client, $companyId, 'NC', []);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<mixed>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['items']);
    }

    public function testValidatingRequiresAtLeastOneLine(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $draftId = $this->createDraft($client, $companyId, 'FT', []);

        $errors = $this->validate($client, $companyId, $draftId);

        self::assertContains(['field' => 'lines', 'message' => 'At least one line is required.'], $errors);
    }

    public function testValidatingRequiresAnExemptionReasonOnAZeroRateLine(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $draftId = $this->createDraft($client, $companyId, 'FT', [
            'lines' => [['quantity' => '1', 'unit_price' => '100.00', 'tax_region' => 'PT', 'tax_code' => 'ISE']],
        ]);

        $errors = $this->validate($client, $companyId, $draftId);

        self::assertContains(['field' => 'lines[0].exemption_reason_code', 'message' => 'An exemption reason is required when the tax rate is 0%.'], $errors);

        $this->updateDraftPayload($client, $companyId, $draftId, [
            'lines' => [['quantity' => '1', 'unit_price' => '100.00', 'tax_region' => 'PT', 'tax_code' => 'ISE', 'exemption_reason_code' => 'M99']],
        ]);
        self::assertSame([], $this->validate($client, $companyId, $draftId));
    }

    public function testValidatingRequiresReferencesForCreditNotes(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $draftId = $this->createDraft($client, $companyId, 'NC', [
            'lines' => [['quantity' => '1', 'unit_price' => '10.00', 'tax_region' => 'PT', 'tax_code' => 'NOR']],
        ]);

        $errors = $this->validate($client, $companyId, $draftId);
        self::assertContains(['field' => 'references', 'message' => 'At least one reference to the original document is required for credit/debit notes.'], $errors);

        $this->updateDraftPayload($client, $companyId, $draftId, [
            'lines' => [['quantity' => '1', 'unit_price' => '10.00', 'tax_region' => 'PT', 'tax_code' => 'NOR']],
            'references' => [['referenced_document_no' => 'FT 2026A/1', 'reason' => 'Devolução']],
        ]);
        self::assertSame([], $this->validate($client, $companyId, $draftId));
    }

    public function testValidatingChecksTheCustomerResolvesAndTheSeriesMatchesTheDocumentType(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/series", server: self::HEADERS, content: json_encode([
            'document_type' => 'NC', 'code' => '2026A',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $seriesCreated */
        $seriesCreated = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $draftId = $this->createDraft($client, $companyId, 'FT', [
            'lines' => [['quantity' => '1', 'unit_price' => '10.00', 'tax_region' => 'PT', 'tax_code' => 'NOR']],
            'series_id' => $seriesCreated['id'],
            'customer_id' => '00000000-0000-7000-8000-000000000000',
        ]);

        $errors = $this->validate($client, $companyId, $draftId);

        self::assertContains(['field' => 'customer_id', 'message' => 'This customer does not exist.'], $errors);
        self::assertContains(['field' => 'series_id', 'message' => 'This series is for document type "NC", not "FT".'], $errors);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createDraft(KernelBrowser $client, string $companyId, string $documentType, array $payload): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => $documentType,
            'payload' => $payload,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $created['id'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateDraftPayload(KernelBrowser $client, string $companyId, string $draftId, array $payload): void
    {
        $client->request('PUT', "/api/v1/companies/{$companyId}/drafts/{$draftId}", server: self::HEADERS, content: json_encode([
            'payload' => $payload,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function validate(KernelBrowser $client, string $companyId, string $draftId): array
    {
        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$draftId}/validate", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{errors: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $body['errors'];
    }

    private function createCompany(KernelBrowser $client, string $legalName = 'A Company Lda'): string
    {
        $client->request('POST', '/api/v1/companies', server: self::HEADERS, content: json_encode([
            'nif' => $this->uniqueNif(),
            'legal_name' => $legalName,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $created['id'];
    }

    private function registerAndLogIn(KernelBrowser $client): UserId
    {
        $email = $this->uniqueEmail();
        $password = 'owner-password';

        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = static::getContainer()->get(PasswordHasher::class);

        $user = User::register(UserId::generate(), $email, 'Flow User', $hasher->hash($password), new \DateTimeImmutable());
        $user->changePassword($hasher->hash($password), new \DateTimeImmutable());
        $users->save($user);

        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $email,
            'password' => $password,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        return $user->id();
    }

    private function uniqueEmail(): string
    {
        return \sprintf('drafts-%s@example.test', bin2hex(random_bytes(8)));
    }

    private function uniqueNif(): string
    {
        do {
            $prefix = (string) random_int(10_000_000, 99_999_999);
            $sum = 0;
            for ($position = 0; $position < 8; ++$position) {
                $sum += (int) $prefix[$position] * (9 - $position);
            }
            $remainder = $sum % 11;
            $checkDigit = $remainder < 2 ? 0 : 11 - $remainder;
            $nif = $prefix.$checkDigit;
        } while (!Nif::isValid($nif));

        return $nif;
    }
}
