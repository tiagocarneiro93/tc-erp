<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fiscal;

use App\Fiscal\Domain\Signing\DocumentSigner;
use App\Fiscal\Domain\Signing\SigningMessage;
use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Decimal\Money;
use App\Shared\Domain\Nif;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * docs/plans/phase-2.md task 2.6/CLAUDE.md's required concurrency test
 * family: parallel issuance against one series must produce no gaps, no
 * duplicate numbers, and a valid unbroken hash chain. `WebTestCase`'s
 * `KernelBrowser` runs in-process and cannot exercise real concurrency
 * (one PHP process, one request at a time), so this spins up PHP's own
 * built-in web server with `PHP_CLI_SERVER_WORKERS` (multiple real worker
 * processes) and fires genuinely overlapping requests at it with
 * `curl_multi`.
 */
final class IssuanceConcurrencyTest extends KernelTestCase
{
    private const HEADERS = ['Content-Type: application/json', 'X-Requested-With: XMLHttpRequest'];
    private const CONCURRENT_REQUESTS = 6;

    /**
     * @var resource|false|null
     */
    private $serverProcess;

    private string $baseUrl = '';

    /**
     * @var non-empty-string
     */
    private string $cookieJar = 'unset';

    protected function setUp(): void
    {
        $port = random_int(20000, 60000);
        $this->baseUrl = "http://127.0.0.1:{$port}";

        $cookieJar = tempnam(sys_get_temp_dir(), 'issuance-cookies-');
        if (false === $cookieJar) {
            self::fail('Could not create a temp cookie jar file.');
        }
        $this->cookieJar = $cookieJar;

        $projectDir = \dirname(__DIR__, 3);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $this->serverProcess = proc_open(
            // PHP's built-in server's default php.ini has variables_order=GPCS
            // (no "E"), so $_ENV stays empty regardless of the real process
            // environment set below — Symfony's Runtime/Dotenv then falls
            // back to .env's APP_ENV=dev default instead of the "test" this
            // spawned server actually needs (wrong DB, wrong everything).
            // -d variables_order=EGPCS makes $_ENV reflect the environment
            // actually passed here.
            ['php', '-d', 'variables_order=EGPCS', '-S', "127.0.0.1:{$port}", '-t', 'public', 'public/index.php'],
            $descriptors,
            $pipes,
            $projectDir,
            ['APP_ENV' => 'test', 'PHP_CLI_SERVER_WORKERS' => (string) self::CONCURRENT_REQUESTS] + getenv(),
        );
        self::assertIsResource($this->serverProcess);
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $this->waitForServerToAcceptConnections();
    }

    protected function tearDown(): void
    {
        if (\is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }
        @unlink($this->cookieJar);
    }

    public function testParallelIssuanceOnOneSeriesHasNoGapsNoDuplicatesAndAValidChain(): void
    {
        $this->registerAndLogIn();
        $companyId = $this->createCompany();
        $seriesId = $this->createSeries($companyId, 'FT', '2026A');
        $this->activateSeries($companyId, $seriesId);

        $draftIds = [];
        for ($i = 0; $i < self::CONCURRENT_REQUESTS; ++$i) {
            $draftIds[] = $this->createDraft($companyId, $seriesId, $i);
        }

        $responses = $this->issueAllConcurrently($companyId, $draftIds);

        foreach ($responses as $i => $response) {
            self::assertSame(201, $response['status'], \sprintf('Request %d must succeed: %s', $i, $response['body']));
        }

        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));

        /** @var list<array{number: int, hash: string, document_no: string, issue_date: string, system_entry_at: string, gross_total: string}> $rows */
        $rows = $connection->fetchAllAssociative(
            'SELECT number, hash, document_no, issue_date, system_entry_at, gross_total FROM documents WHERE company_id = ? AND series_id = ? ORDER BY number ASC',
            [$companyId, $seriesId],
        );

        self::assertCount(self::CONCURRENT_REQUESTS, $rows, 'Every concurrent request must have produced exactly one document.');

        $numbers = array_map(static fn (array $row): int => (int) $row['number'], $rows);
        self::assertSame(range(1, self::CONCURRENT_REQUESTS), $numbers, 'Numbers must be contiguous, starting at 1, with no gaps or duplicates.');

        /** @var DocumentSigner $signer */
        $signer = self::getContainer()->get(DocumentSigner::class);
        $previousHash = null;

        foreach ($rows as $row) {
            $message = SigningMessage::build(
                new \DateTimeImmutable($row['issue_date']),
                new \DateTimeImmutable($row['system_entry_at']),
                $row['document_no'],
                Money::fromString($row['gross_total']),
                $previousHash,
            );

            // RSA + PKCS#1 v1.5 is deterministic (no random salt), so
            // re-signing the exact same message must reproduce the exact
            // same hash the issuance transaction stored — proving this
            // document really was chained to its predecessor's hash.
            self::assertSame($signer->sign($message)->hash(), $row['hash'], \sprintf('Document %s must be chained to the previous document\'s hash.', $row['document_no']));

            $previousHash = $row['hash'];
        }

        $connection->close();
    }

    /**
     * @param list<string> $draftIds
     *
     * @return list<array{status: int, body: string}>
     */
    private function issueAllConcurrently(string $companyId, array $draftIds): array
    {
        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($draftIds as $i => $draftId) {
            $ch = curl_init("{$this->baseUrl}/api/v1/companies/{$companyId}/documents/drafts/{$draftId}/issue");
            curl_setopt_array($ch, [
                \CURLOPT_RETURNTRANSFER => true,
                \CURLOPT_POST => true,
                \CURLOPT_POSTFIELDS => '{}',
                \CURLOPT_HTTPHEADER => [...self::HEADERS, "Idempotency-Key: concurrent-{$i}"],
                \CURLOPT_COOKIEFILE => $this->cookieJar,
                \CURLOPT_COOKIEJAR => $this->cookieJar,
            ]);
            curl_multi_add_handle($multiHandle, $ch);
            $handles[] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        $responses = [];
        foreach ($handles as $ch) {
            $responses[] = [
                'status' => curl_getinfo($ch, \CURLINFO_HTTP_CODE),
                'body' => (string) curl_multi_getcontent($ch),
            ];
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        curl_multi_close($multiHandle);

        return $responses;
    }

    private function createDraft(string $companyId, string $seriesId, int $index): string
    {
        $body = $this->request('POST', "/api/v1/companies/{$companyId}/drafts", [
            'document_type' => 'FT',
            'payload' => [
                'series_id' => $seriesId,
                'pricing_mode' => 'net',
                'rounding_method' => 'per_line',
                'date' => '2026-01-01',
                'lines' => [[
                    'product_code' => \sprintf('SKU-%d', $index),
                    'description' => 'Concurrency test product',
                    'product_type' => 'P',
                    'unit_code' => 'UN',
                    'quantity' => '1',
                    'unit_price' => '10.00',
                    'tax_region' => 'PT',
                    'tax_code' => 'NOR',
                ]],
            ],
        ]);

        /** @var array{id: string} $decoded */
        $decoded = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);

        return $decoded['id'];
    }

    private function createSeries(string $companyId, string $documentType, string $code): string
    {
        $body = $this->request('POST', "/api/v1/companies/{$companyId}/series", [
            'document_type' => $documentType,
            'code' => $code,
        ]);
        /** @var array{id: string} $decoded */
        $decoded = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);

        return $decoded['id'];
    }

    private function activateSeries(string $companyId, string $seriesId): void
    {
        $this->request('POST', "/api/v1/companies/{$companyId}/series/{$seriesId}/activate", []);
    }

    private function createCompany(): string
    {
        $body = $this->request('POST', '/api/v1/companies', [
            'nif' => $this->uniqueNif(),
            'legal_name' => 'Concurrency Test Lda',
        ]);
        /** @var array{id: string} $decoded */
        $decoded = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);

        return $decoded['id'];
    }

    private function registerAndLogIn(): void
    {
        $email = \sprintf('issuance-concurrency-%s@example.test', bin2hex(random_bytes(8)));
        $password = 'owner-password';

        self::bootKernel();
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = self::getContainer()->get(PasswordHasher::class);

        $user = User::register(UserId::generate(), $email, 'Concurrency Test User', $hasher->hash($password), new \DateTimeImmutable());
        $user->changePassword($hasher->hash($password), new \DateTimeImmutable());
        $users->save($user);
        self::ensureKernelShutdown();

        $this->request('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * @param non-empty-string     $method
     * @param array<string, mixed> $payload
     */
    private function request(string $method, string $path, array $payload): string
    {
        $ch = curl_init($this->baseUrl.$path);
        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_CUSTOMREQUEST => $method,
            \CURLOPT_POSTFIELDS => json_encode($payload, \JSON_THROW_ON_ERROR),
            \CURLOPT_HTTPHEADER => self::HEADERS,
            \CURLOPT_COOKIEFILE => $this->cookieJar,
            \CURLOPT_COOKIEJAR => $this->cookieJar,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);

        self::assertIsString($body);
        self::assertGreaterThanOrEqual(200, $status);
        self::assertLessThan(300, $status, \sprintf('%s %s failed: %s', $method, $path, $body));

        return $body;
    }

    private function waitForServerToAcceptConnections(): void
    {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', (int) parse_url($this->baseUrl, \PHP_URL_PORT), timeout: 1);

            if (false !== $connection) {
                fclose($connection);

                return;
            }

            usleep(50_000);
        }

        self::fail('The built-in PHP server did not start in time.');
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
