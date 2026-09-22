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
 * docs/plans/phase-2.md task 2.2: CRUD + lifecycle (draft -> active ->
 * finished/cancelled), the UNIQUE(company_id, document_type, code)
 * constraint, and the "no validation code, cannot issue" rule surfaced via
 * `can_issue`.
 */
final class SeriesControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/companies/00000000-0000-7000-8000-000000000000/series');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreatingASeriesStartsDraftAndCannotIssue(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');

        $client->request('GET', "/api/v1/companies/{$companyId}/series/{$seriesId}", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{document_type: string, code: string, status: string, validation_code: ?string, can_issue: bool, first_number: int, last_number: ?int} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('FT', $body['document_type']);
        self::assertSame('2026A', $body['code']);
        self::assertSame('draft', $body['status']);
        self::assertNull($body['validation_code']);
        self::assertFalse($body['can_issue']);
        self::assertSame(1, $body['first_number']);
        self::assertNull($body['last_number']);
    }

    public function testCreatingWithAnUnknownDocumentTypeIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/series", server: self::HEADERS, content: json_encode([
            'document_type' => 'ZZ',
            'code' => '2026A',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * docs/plans/phase-3.md task 3.1c, `at-ws-series-aspetos-especificos.pdf`
     * §1.3.2 — "AT" is reserved for AT's own programs.
     */
    public function testCreatingWithAReservedAtPrefixedCodeIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/series", server: self::HEADERS, content: json_encode([
            'document_type' => 'FT',
            'code' => 'AT2026A',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreatingADuplicateDocumentTypeAndCodeForTheSameCompanyFails(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $this->createSeries($client, $companyId, 'FT', '2026A');

        $client->request('POST', "/api/v1/companies/{$companyId}/series", server: self::HEADERS, content: json_encode([
            'document_type' => 'FT',
            'code' => '2026A',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdatingASeriesWhileDraft(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');

        $client->request('PUT', "/api/v1/companies/{$companyId}/series/{$seriesId}", server: self::HEADERS, content: json_encode([
            'code' => '2026B',
            'is_training' => true,
            'first_number' => 10,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/series/{$seriesId}", server: self::HEADERS);
        /** @var array{code: string, is_training: bool, first_number: int} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('2026B', $body['code']);
        self::assertTrue($body['is_training']);
        self::assertSame(10, $body['first_number']);
    }

    public function testActivatingRequiresAValidationCodeAndEnablesIssuing(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/activate", server: self::HEADERS, content: json_encode([
            'validation_code' => '',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, 'An empty validation code must be rejected.');

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/activate", server: self::HEADERS, content: json_encode([
            'validation_code' => 'ABC123',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/series/{$seriesId}", server: self::HEADERS);
        /** @var array{status: string, validation_code: ?string, can_issue: bool} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('active', $body['status']);
        self::assertSame('ABC123', $body['validation_code']);
        self::assertTrue($body['can_issue']);
    }

    public function testActivatingTwiceIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');
        $this->activateSeries($client, $companyId, $seriesId);

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/activate", server: self::HEADERS, content: json_encode([
            'validation_code' => 'DEF456',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdatingAnActiveSeriesIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');
        $this->activateSeries($client, $companyId, $seriesId);

        $client->request('PUT', "/api/v1/companies/{$companyId}/series/{$seriesId}", server: self::HEADERS, content: json_encode([
            'code' => '2026B',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFinishingRequiresAnActiveSeries(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/finish", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, 'Finishing a draft series must be rejected.');

        $this->activateSeries($client, $companyId, $seriesId);

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/finish", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/series/{$seriesId}", server: self::HEADERS);
        /** @var array{status: string, can_issue: bool} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('finished', $body['status']);
        self::assertFalse($body['can_issue']);

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/finish", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, 'Finishing an already-finished series must be rejected.');
    }

    public function testCancellingFromDraftAndFromActive(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $draftId = $this->createSeries($client, $companyId, 'FT', '2026A');
        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$draftId}/cancel", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $activeId = $this->createSeries($client, $companyId, 'FT', '2026B');
        $this->activateSeries($client, $companyId, $activeId);
        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$activeId}/cancel", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/series/{$activeId}", server: self::HEADERS);
        /** @var array{status: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('cancelled', $body['status']);

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$activeId}/cancel", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, 'Cancelling an already-cancelled series must be rejected.');
    }

    public function testListingReturnsEverySeriesForTheCompany(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $this->createSeries($client, $companyId, 'FT', '2026A');
        $this->createSeries($client, $companyId, 'NC', '2026A');

        $client->request('GET', "/api/v1/companies/{$companyId}/series", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<array{document_type: string, code: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['items']);
    }

    public function testGettingAnUnknownSeriesIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/series/00000000-0000-7000-8000-000000000000", server: self::HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function createSeries(KernelBrowser $client, string $companyId, string $documentType, string $code): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/series", server: self::HEADERS, content: json_encode([
            'document_type' => $documentType,
            'code' => $code,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $created['id'];
    }

    private function activateSeries(KernelBrowser $client, string $companyId, string $seriesId): void
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/activate", server: self::HEADERS, content: json_encode([
            'validation_code' => 'ABC123',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
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
        return \sprintf('series-%s@example.test', bin2hex(random_bytes(8)));
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
