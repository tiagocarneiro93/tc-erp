<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fiscal;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * docs/plans/phase-2.md task 2.9's own accept criterion: "the concurrency
 * test family from task 2.6 (no gaps/duplicates) applies to receipt
 * numbering too, on its own series" — {@see IssuanceConcurrencyTest} is
 * the original for `documents`; this is the same technique against
 * `receipts`, each concurrent receipt allocated to its own invoice so the
 * only thing genuinely contended is the RG series' row lock.
 */
final class ReceiptIssuanceConcurrencyTest extends KernelTestCase
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

        $cookieJar = tempnam(sys_get_temp_dir(), 'receipt-issuance-cookies-');
        if (false === $cookieJar) {
            self::fail('Could not create a temp cookie jar file.');
        }
        $this->cookieJar = $cookieJar;

        $projectDir = \dirname(__DIR__, 3);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $this->serverProcess = proc_open(
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

    public function testParallelReceiptIssuanceOnOneSeriesHasNoGapsOrDuplicates(): void
    {
        $this->registerAndLogIn();
        $companyId = $this->createCompany();
        $rgSeriesId = $this->createSeries($companyId, 'RG', '2026A');
        $this->activateSeries($companyId, $rgSeriesId);

        $invoiceIds = [];
        for ($i = 0; $i < self::CONCURRENT_REQUESTS; ++$i) {
            $invoiceIds[] = $this->issueInvoice($companyId, $i);
        }

        $responses = $this->issueReceiptsConcurrently($companyId, $rgSeriesId, $invoiceIds);

        foreach ($responses as $i => $response) {
            self::assertSame(201, $response['status'], \sprintf('Request %d must succeed: %s', $i, $response['body']));
        }

        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));

        /** @var list<array{number: int}> $rows */
        $rows = $connection->fetchAllAssociative(
            'SELECT number FROM receipts WHERE company_id = ? AND series_id = ? ORDER BY number ASC',
            [$companyId, $rgSeriesId],
        );
        $connection->close();

        self::assertCount(self::CONCURRENT_REQUESTS, $rows, 'Every concurrent request must have produced exactly one receipt.');

        $numbers = array_map(static fn (array $row): int => (int) $row['number'], $rows);
        self::assertSame(range(1, self::CONCURRENT_REQUESTS), $numbers, 'Numbers must be contiguous, starting at 1, with no gaps or duplicates.');
    }

    /**
     * @param list<string> $invoiceIds
     *
     * @return list<array{status: int, body: string}>
     */
    private function issueReceiptsConcurrently(string $companyId, string $seriesId, array $invoiceIds): array
    {
        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($invoiceIds as $i => $invoiceId) {
            $ch = curl_init("{$this->baseUrl}/api/v1/companies/{$companyId}/receipts");
            curl_setopt_array($ch, [
                \CURLOPT_RETURNTRANSFER => true,
                \CURLOPT_POST => true,
                \CURLOPT_POSTFIELDS => json_encode([
                    'series_id' => $seriesId,
                    'payment_method' => 'cash',
                    'allocations' => [['document_id' => $invoiceId, 'amount' => '10.00']],
                ], \JSON_THROW_ON_ERROR),
                \CURLOPT_HTTPHEADER => [...self::HEADERS, "Idempotency-Key: concurrent-receipt-{$i}"],
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

    private function issueInvoice(string $companyId, int $index): string
    {
        $ftSeriesId = $this->createSeries($companyId, 'FT', \sprintf('2026A-%d', $index));
        $this->activateSeries($companyId, $ftSeriesId);

        $body = $this->request('POST', "/api/v1/companies/{$companyId}/drafts", [
            'document_type' => 'FT',
            'payload' => [
                'series_id' => $ftSeriesId,
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
                    'tax_code' => 'ISE',
                    'exemption_reason_code' => 'M99',
                ]],
            ],
        ]);
        /** @var array{id: string} $draft */
        $draft = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);

        $issued = $this->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draft['id']}/issue", [], ["Idempotency-Key: issue-{$draft['id']}"]);
        /** @var array{id: string} $document */
        $document = json_decode($issued, true, flags: \JSON_THROW_ON_ERROR);

        return $document['id'];
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
            'legal_name' => 'Receipt Concurrency Test Lda',
        ]);
        /** @var array{id: string} $decoded */
        $decoded = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);

        return $decoded['id'];
    }

    private function registerAndLogIn(): void
    {
        $email = \sprintf('receipt-concurrency-%s@example.test', bin2hex(random_bytes(8)));
        $password = 'owner-password';

        self::bootKernel();
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = self::getContainer()->get(PasswordHasher::class);

        $user = User::register(UserId::generate(), $email, 'Receipt Concurrency Test User', $hasher->hash($password), new \DateTimeImmutable());
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
     * @param list<string>         $extraHeaders
     */
    private function request(string $method, string $path, array $payload, array $extraHeaders = []): string
    {
        $ch = curl_init($this->baseUrl.$path);
        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_CUSTOMREQUEST => $method,
            \CURLOPT_POSTFIELDS => json_encode($payload, \JSON_THROW_ON_ERROR),
            \CURLOPT_HTTPHEADER => [...self::HEADERS, ...$extraHeaders],
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
