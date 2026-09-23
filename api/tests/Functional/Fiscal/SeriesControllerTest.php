<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fiscal;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\AtIntegration\SeriesCancellation;
use App\Shared\Domain\AtIntegration\SeriesFinalization;
use App\Shared\Domain\AtIntegration\SeriesRegistration;
use App\Shared\Domain\AtIntegration\SeriesWebserviceClient;
use App\Shared\Domain\AtIntegration\SeriesWebserviceResult;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;
use Doctrine\DBAL\Connection;
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

    /**
     * docs/plans/phase-3.md task 3.1: activating no longer takes a
     * manually-entered validation code — it's obtained from AT
     * (`registarSerie`) synchronously. In the test suite that's
     * `FakeSeriesWebserviceClient` (`config/services_test.yaml`), which
     * always succeeds with an 8-hex-character code.
     */
    public function testActivatingObtainsAValidationCodeFromAtAndEnablesIssuing(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/activate", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/series/{$seriesId}", server: self::HEADERS);
        /** @var array{status: string, validation_code: ?string, can_issue: bool} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('active', $body['status']);
        self::assertMatchesRegularExpression('/^[0-9A-F]{8}$/', (string) $body['validation_code']);
        self::assertTrue($body['can_issue']);
    }

    /**
     * Found live (2026-09-23): a real AT rejection surfaced as 422 in the
     * UI, but `at_communications` had no row for it at all — the exact
     * audit trail that rejection was supposed to leave (§6.12,
     * `docs/plans/phase-3.md` task 3.1's own accept criterion: "a row for
     * every series register/finish/cancel attempt, successful or not").
     * Root cause: `command.bus`'s `doctrine_transaction` middleware wraps
     * the whole handler call in one transaction; `ActivateSeriesHandler`
     * wrote the audit row via `recordResolved()` and then threw
     * `SeriesWebserviceRejected` to signal the 422 — and that throw rolled
     * the entire transaction back, undoing the very row meant to survive
     * the rejection. Never caught before because `FakeSeriesWebserviceClient`
     * always accepts, so this code path never actually ran in the everyday
     * suite until a real AT rejection hit it.
     */
    public function testActivationRejectedByAtStillLeavesAnAuditRow(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');

        // Without this, KernelBrowser reboots the kernel (and rebuilds the
        // container, discarding the override below) before the next request.
        $client->disableReboot();

        self::getContainer()->set(\App\AtIntegration\Infrastructure\Fake\FakeSeriesWebserviceClient::class, new class implements SeriesWebserviceClient {
            public function register(CompanyId $companyId, SeriesRegistration $request): SeriesWebserviceResult
            {
                return new SeriesWebserviceResult(false, 4001, 'Rejected for test.', null);
            }

            public function finish(CompanyId $companyId, SeriesFinalization $request): SeriesWebserviceResult
            {
                throw new \LogicException('Not used by this test.');
            }

            public function cancel(CompanyId $companyId, SeriesCancellation $request): SeriesWebserviceResult
            {
                throw new \LogicException('Not used by this test.');
            }
        });

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/activate", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        /** @var CompanyContext $companyContext */
        $companyContext = self::getContainer()->get(CompanyContext::class);
        $companyContext->set(CompanyId::fromString($companyId));
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        $connection->beginTransaction();
        try {
            $row = $connection->fetchAssociative(
                'SELECT status, response_code, response_message FROM at_communications WHERE subject_id = ? AND kind = ?',
                [$seriesId, 'series_register'],
            );
        } finally {
            $connection->rollBack();
        }

        self::assertIsArray($row, 'Expected an at_communications row for the rejected registration attempt.');
        self::assertSame('rejected', $row['status']);
        self::assertSame('4001', $row['response_code']);
        self::assertSame('Rejected for test.', $row['response_message']);
    }

    public function testActivatingTwiceIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');
        $this->activateSeries($client, $companyId, $seriesId);

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/activate", server: self::HEADERS);

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

        // docs/plans/phase-3.md task 3.1: finishing needs at least one
        // issued document (at-ws-series-aspetos-especificos.pdf §2.3.2's
        // seqUltimoDocEmitido must be positive).
        $this->issueInvoice($client, $companyId, $seriesId);

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

    private function issueInvoice(KernelBrowser $client, string $companyId, string $seriesId): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => 'FT',
            'payload' => [
                'series_id' => $seriesId,
                'pricing_mode' => 'net',
                'rounding_method' => 'per_line',
                'date' => '2026-01-01',
                'lines' => [
                    ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => '10.00', 'tax_region' => 'PT', 'tax_code' => 'ISE', 'exemption_reason_code' => 'M99'],
                ],
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
        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/activate", server: self::HEADERS);
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
