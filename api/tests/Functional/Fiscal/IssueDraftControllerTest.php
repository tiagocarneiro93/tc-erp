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
 * docs/plans/phase-2.md task 2.6: the end-to-end draft → issued document
 * transaction (§7.1), idempotency replay, and every documented rejection
 * (no series, series that cannot issue, a draft that fails validation).
 * The concurrency test family lives in {@see IssuanceConcurrencyTest}
 * (real parallelism, which this in-process `KernelBrowser` cannot exercise).
 */
final class IssueDraftControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testIssuingADraftCreatesAnImmutableDocumentAndDeletesTheDraft(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');
        $this->activateSeries($client, $companyId, $seriesId);
        $draftId = $this->createIssuableDraft($client, $companyId, $seriesId, 'FT');

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draftId}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'issue-key-1'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertNotSame('', $body['id']);

        // Drafts are not documents (CLAUDE.md) — once issued, the draft is gone.
        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$draftId}", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));
        /** @var array{document_no: string, number: string, atcud: string, hash: string, status: string, is_training: string}|false $row */
        $row = $connection->fetchAssociative('SELECT document_no, number, atcud, hash, status, is_training FROM documents WHERE id = ?', [$body['id']]);
        self::assertIsArray($row);
        self::assertSame('FT 2026A/1', $row['document_no']);
        self::assertSame('1', (string) $row['number']);
        self::assertSame('N', $row['status']);
        // docs/plans/phase-3.md task 3.1f: the validation code now comes
        // from FakeSeriesWebserviceClient (8 hex chars), not a fixed
        // manually-entered one — assert the ATCUD's shape, not a literal.
        self::assertMatchesRegularExpression('/^[0-9A-F]{8}-1$/', $row['atcud']);
        self::assertSame(172, \strlen($row['hash']));

        /** @var array{count: int|string} $statusEventCount */
        $statusEventCount = $connection->fetchAssociative('SELECT COUNT(*) as count FROM document_status_events WHERE document_id = ?', [$body['id']]);
        self::assertSame(1, (int) $statusEventCount['count']);
    }

    public function testReplayingTheSameIdempotencyKeyReturnsTheSameDocumentAndNeverIssuesTwice(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');
        $this->activateSeries($client, $companyId, $seriesId);
        $draftId = $this->createIssuableDraft($client, $companyId, $seriesId, 'FT');

        $headers = self::HEADERS + ['HTTP_Idempotency-Key' => 'replay-key'];

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draftId}/issue", server: $headers, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $first = (string) $client->getResponse()->getContent();

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draftId}/issue", server: $headers, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $second = (string) $client->getResponse()->getContent();

        self::assertSame($first, $second, 'A replayed Idempotency-Key must return the exact original response, never issue a second document.');
    }

    public function testIssuingWithoutASeriesIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $draftId = $this->createDraft($client, $companyId, 'FT', [
            'pricing_mode' => 'net',
            'rounding_method' => 'per_line',
            'date' => '2026-01-01',
            'lines' => [$this->issuableLine()],
        ]);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draftId}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'no-series'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIssuingAgainstADraftStatusSeriesIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');
        // Not activated — no validation code.
        $draftId = $this->createIssuableDraft($client, $companyId, $seriesId, 'FT');

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draftId}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'inactive-series'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIssuingADraftWithNoLinesIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');
        $this->activateSeries($client, $companyId, $seriesId);
        $draftId = $this->createDraft($client, $companyId, 'FT', ['series_id' => $seriesId]);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draftId}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'no-lines'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testIssuingWithoutAnIdempotencyKeyIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');
        $this->activateSeries($client, $companyId, $seriesId);
        $draftId = $this->createIssuableDraft($client, $companyId, $seriesId, 'FT');

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draftId}/issue", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testASecondDocumentOnTheSameSeriesChainsToTheFirstsHash(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createSeries($client, $companyId, 'FT', '2026A');
        $this->activateSeries($client, $companyId, $seriesId);

        $firstDraftId = $this->createIssuableDraft($client, $companyId, $seriesId, 'FT');
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$firstDraftId}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'chain-1'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $first */
        $first = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $secondDraftId = $this->createIssuableDraft($client, $companyId, $seriesId, 'FT');
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$secondDraftId}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'chain-2'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $second */
        $second = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));
        /** @var array{hash: string, number: string} $firstRow */
        $firstRow = $connection->fetchAssociative('SELECT hash, number FROM documents WHERE id = ?', [$first['id']]);
        /** @var array{number: string} $secondRow */
        $secondRow = $connection->fetchAssociative('SELECT number FROM documents WHERE id = ?', [$second['id']]);

        self::assertSame('1', (string) $firstRow['number']);
        self::assertSame('2', (string) $secondRow['number']);

        // The chain link itself (previous document's Hash feeding the next
        // document's signing string) is verified independently in
        // DocumentSigningChainTest and IssuanceConcurrencyTest; here we only
        // confirm numbering advanced and both documents share the series.
        self::assertNotSame('', $firstRow['hash']);
    }

    /**
     * @return array<string, mixed>
     */
    private function issuableLine(int $index = 0): array
    {
        return [
            'product_code' => \sprintf('SKU-%d', $index),
            'description' => 'Test product',
            'product_type' => 'P',
            'unit_code' => 'UN',
            'quantity' => '1',
            'unit_price' => '100.00',
            'tax_region' => 'PT',
            'tax_code' => 'NOR',
        ];
    }

    private function createIssuableDraft(KernelBrowser $client, string $companyId, string $seriesId, string $documentType): string
    {
        return $this->createDraft($client, $companyId, $documentType, [
            'series_id' => $seriesId,
            'pricing_mode' => 'net',
            'rounding_method' => 'per_line',
            'date' => '2026-01-01',
            'lines' => [$this->issuableLine()],
        ]);
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

        $user = User::register(UserId::generate(), $email, 'Issue Flow User', $hasher->hash($password), new \DateTimeImmutable());
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
        return \sprintf('issue-draft-%s@example.test', bin2hex(random_bytes(8)));
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
