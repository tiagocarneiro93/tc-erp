<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fiscal;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-2.md task 2.8: `POST /documents/{id}/convert` prefills
 * a draft of the target type from a working document's still-pending
 * quantities, and §6.8's "converting a working document fully closes it
 * (status F); a partial conversion leaves the correct remainder open."
 * The "não serve de fatura" mention (Despacho §1.2) is covered here too,
 * via the draft it's exposed on ({@see DraftsController}).
 */
final class ConvertDocumentControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testAWorkingDocumentDraftCarriesTheNotAnInvoiceMentionButAnInvoiceDoesNot(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => 'NE',
            'payload' => ['lines' => []],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $neDraft */
        $neDraft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$neDraft['id']}", server: self::HEADERS);
        /** @var array{mention: ?string} $neView */
        $neView = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Este documento não serve de fatura', $neView['mention']);

        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => 'FT',
            'payload' => ['lines' => []],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $ftDraft */
        $ftDraft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$ftDraft['id']}", server: self::HEADERS);
        /** @var array{mention: ?string} $ftView */
        $ftView = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertNull($ftView['mention']);
    }

    public function testFullConversionOfAWorkingDocumentClosesIt(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $neSeriesId = $this->createActiveSeries($client, $companyId, 'NE', '2026A');
        $ftSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A');

        $neId = $this->issueDocument($client, $companyId, $neSeriesId, 'NE', [
            ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '3', 'unit_price' => '10.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
        ]);

        $this->convertAndIssue($client, $companyId, $neId, 'FT', $ftSeriesId, null);

        self::assertSame('F', $this->documentStatus($companyId, $neId));
    }

    public function testPartialConversionLeavesTheDocumentOpenAndASecondConversionCloses(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $neSeriesId = $this->createActiveSeries($client, $companyId, 'NE', '2026A');
        $ftSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A');

        $neId = $this->issueDocument($client, $companyId, $neSeriesId, 'NE', [
            ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '10', 'unit_price' => '5.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
        ]);

        // Convert only 4 of the 10 pending units.
        $this->convertAndIssue($client, $companyId, $neId, 'FT', $ftSeriesId, '4');
        self::assertSame('N', $this->documentStatus($companyId, $neId));

        // A second conversion is prefilled with exactly the 6 still pending.
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$neId}/convert", server: self::HEADERS, content: json_encode(['document_type' => 'FT'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $secondDraft */
        $secondDraft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$secondDraft['id']}", server: self::HEADERS);
        /** @var array{payload: array{lines: list<array{quantity: string}>}} $draftView */
        $draftView = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('6.000000', $draftView['payload']['lines'][0]['quantity']);

        $this->setSeriesAndIssue($client, $companyId, $secondDraft['id'], $ftSeriesId);
        self::assertSame('F', $this->documentStatus($companyId, $neId));

        // Nothing left to convert now.
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$neId}/convert", server: self::HEADERS, content: json_encode(['document_type' => 'FT'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testConversionOfACancelledDocumentIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $neSeriesId = $this->createActiveSeries($client, $companyId, 'NE', '2026A');

        $neId = $this->issueDocument($client, $companyId, $neSeriesId, 'NE', [
            ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => '10.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
        ]);

        // Cancellation itself is task 2.10 — not built yet (same technique
        // as CreditNoteControllerTest uses for the same reason).
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));
        $connection->executeStatement('INSERT INTO document_status_events (id, company_id, document_id, status, occurred_at) VALUES (gen_random_uuid(), ?, ?, ?, now())', [$companyId, $neId, 'A']);
        $connection->executeStatement('UPDATE documents SET status = ? WHERE company_id = ? AND id = ?', ['A', $companyId, $neId]);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$neId}/convert", server: self::HEADERS, content: json_encode(['document_type' => 'FT'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testConversionIntoAnUnsupportedTargetTypeIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $neSeriesId = $this->createActiveSeries($client, $companyId, 'NE', '2026A');

        $neId = $this->issueDocument($client, $companyId, $neSeriesId, 'NE', [
            ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => '10.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
        ]);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$neId}/convert", server: self::HEADERS, content: json_encode(['document_type' => 'NC'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testConversionOfANonWorkingDocumentIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $ftSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A');

        $ftId = $this->issueDocument($client, $companyId, $ftSeriesId, 'FT', [
            ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => '10.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
        ]);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$ftId}/convert", server: self::HEADERS, content: json_encode(['document_type' => 'FT'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testConversionOfAnUnknownDocumentIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/00000000-0000-7000-8000-000000000000/convert", server: self::HEADERS, content: json_encode(['document_type' => 'FT'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Creates a conversion draft, optionally reduces the (single) line's
     * quantity for a partial conversion, sets the target series and
     * issues it.
     */
    private function convertAndIssue(KernelBrowser $client, string $companyId, string $sourceDocumentId, string $targetType, string $targetSeriesId, ?string $partialQuantity): void
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$sourceDocumentId}/convert", server: self::HEADERS, content: json_encode(['document_type' => $targetType], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $draft */
        $draft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        if (null !== $partialQuantity) {
            $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$draft['id']}", server: self::HEADERS);
            /** @var array{payload: array<string, mixed>} $draftView */
            $draftView = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
            $payload = $draftView['payload'];
            /** @var list<array<string, mixed>> $lines */
            $lines = $payload['lines'];
            $lines[0]['quantity'] = $partialQuantity;
            $payload['lines'] = $lines;
            $payload['series_id'] = $targetSeriesId;

            $client->request('PUT', "/api/v1/companies/{$companyId}/drafts/{$draft['id']}", server: self::HEADERS, content: json_encode(['payload' => $payload], \JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        } else {
            $this->setSeriesAndIssue($client, $companyId, $draft['id'], $targetSeriesId);

            return;
        }

        $this->issueDraft($client, $companyId, $draft['id']);
    }

    private function setSeriesAndIssue(KernelBrowser $client, string $companyId, string $draftId, string $seriesId): void
    {
        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$draftId}", server: self::HEADERS);
        /** @var array{payload: array<string, mixed>} $draftView */
        $draftView = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $payload = $draftView['payload'];
        $payload['series_id'] = $seriesId;

        $client->request('PUT', "/api/v1/companies/{$companyId}/drafts/{$draftId}", server: self::HEADERS, content: json_encode(['payload' => $payload], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->issueDraft($client, $companyId, $draftId);
    }

    private function issueDraft(KernelBrowser $client, string $companyId, string $draftId): void
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draftId}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'convert-'.$draftId], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    private function documentStatus(string $companyId, string $documentId): string
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));
        /** @var array{status: string} $row */
        $row = $connection->fetchAssociative('SELECT status FROM documents WHERE company_id = ? AND id = ?', [$companyId, $documentId]);

        return $row['status'];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function issueDocument(KernelBrowser $client, string $companyId, string $seriesId, string $documentType, array $lines): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => $documentType,
            'payload' => [
                'series_id' => $seriesId,
                'pricing_mode' => 'net',
                'rounding_method' => 'per_line',
                'date' => '2026-01-01',
                'lines' => $lines,
            ],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $draft */
        $draft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draft['id']}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'issue-'.$draft['id']], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $document */
        $document = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $document['id'];
    }

    private function createActiveSeries(KernelBrowser $client, string $companyId, string $documentType, string $code): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/series", server: self::HEADERS, content: json_encode([
            'document_type' => $documentType,
            'code' => $code,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$created['id']}/activate", server: self::HEADERS, content: json_encode([
            'validation_code' => 'ABC123',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        return $created['id'];
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

        $user = User::register(UserId::generate(), $email, 'Convert Document Flow User', $hasher->hash($password), new \DateTimeImmutable());
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
        return \sprintf('convert-doc-%s@example.test', bin2hex(random_bytes(8)));
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
