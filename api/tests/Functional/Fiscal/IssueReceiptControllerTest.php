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
 * docs/plans/phase-2.md task 2.9: `POST /companies/{c}/receipts` issues a
 * receipt directly (no draft step) against one or more open invoices,
 * unsigned (Despacho 8632/2014 §2.2.3) but still carrying an ATCUD.
 * {@see ReceiptIssuanceConcurrencyTest} covers RG's own series under real
 * concurrency, the same family task 2.6 built for documents.
 */
final class IssueReceiptControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testAReceiptIssuesWithAnAtcudAndSettlesTheInvoice(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $rgSeriesId = $this->createActiveSeries($client, $companyId, 'RG', '2026A');
        $ftId = $this->issueInvoice($client, $companyId, '100.00');

        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'rg-1'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'transfer',
            'allocations' => [['document_id' => $ftId, 'amount' => '100.00']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $receipt */
        $receipt = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        /** @var array{atcud: string, status: string, total: string} $row */
        $row = $this->fetchReceipt($companyId, $receipt['id']);
        self::assertNotSame('', $row['atcud']);
        self::assertSame('N', $row['status']);
        self::assertSame('100.00', $row['total']);

        // The invoice is now fully settled — a further allocation is rejected.
        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'rg-2'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'transfer',
            'allocations' => [['document_id' => $ftId, 'amount' => '0.01']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAPartialPaymentLeavesTheRemainderAllocatable(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $rgSeriesId = $this->createActiveSeries($client, $companyId, 'RG', '2026A');
        $ftId = $this->issueInvoice($client, $companyId, '100.00');

        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'partial-1'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'cash',
            'allocations' => [['document_id' => $ftId, 'amount' => '40.00']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Exceeding the remaining 60.00 is rejected.
        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'partial-2'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'cash',
            'allocations' => [['document_id' => $ftId, 'amount' => '60.01']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Exactly the remaining 60.00 succeeds.
        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'partial-3'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'cash',
            'allocations' => [['document_id' => $ftId, 'amount' => '60.00']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testAllocationAgainstACancelledDocumentIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $rgSeriesId = $this->createActiveSeries($client, $companyId, 'RG', '2026A');
        $ftId = $this->issueInvoice($client, $companyId, '50.00');

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));
        $connection->executeStatement('INSERT INTO document_status_events (id, company_id, document_id, status, occurred_at) VALUES (gen_random_uuid(), ?, ?, ?, now())', [$companyId, $ftId, 'A']);
        $connection->executeStatement('UPDATE documents SET status = ? WHERE company_id = ? AND id = ?', ['A', $companyId, $ftId]);

        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'cancelled-1'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'cash',
            'allocations' => [['document_id' => $ftId, 'amount' => '10.00']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAllocationAgainstACreditNoteIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $rgSeriesId = $this->createActiveSeries($client, $companyId, 'RG', '2026A');
        $ncSeriesId = $this->createActiveSeries($client, $companyId, 'NC', '2026A');
        $ftId = $this->issueInvoice($client, $companyId, '50.00');

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$ftId}/credit-note", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $creditNoteDraft */
        $creditNoteDraft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$creditNoteDraft['id']}", server: self::HEADERS);
        /** @var array{payload: array<string, mixed>} $draftView */
        $draftView = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $payload = $draftView['payload'];
        $payload['series_id'] = $ncSeriesId;

        $client->request('PUT', "/api/v1/companies/{$companyId}/drafts/{$creditNoteDraft['id']}", server: self::HEADERS, content: json_encode(['payload' => $payload], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$creditNoteDraft['id']}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'nc-for-receipt-test'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $ncDocument */
        $ncDocument = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'nc-allocation'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'cash',
            'allocations' => [['document_id' => $ncDocument['id'], 'amount' => '1.00']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testNoAllocationsIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $rgSeriesId = $this->createActiveSeries($client, $companyId, 'RG', '2026A');

        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'no-allocations'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'cash',
            'allocations' => [],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAllocationAgainstAnUnknownDocumentIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $rgSeriesId = $this->createActiveSeries($client, $companyId, 'RG', '2026A');

        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'unknown-document'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'cash',
            'allocations' => [['document_id' => '00000000-0000-7000-8000-000000000000', 'amount' => '1.00']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAnUnknownSeriesIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $ftId = $this->issueInvoice($client, $companyId, '10.00');

        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'unknown-series'], content: json_encode([
            'series_id' => '00000000-0000-7000-8000-000000000000',
            'payment_method' => 'cash',
            'allocations' => [['document_id' => $ftId, 'amount' => '1.00']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function issueInvoice(KernelBrowser $client, string $companyId, string $unitPrice): string
    {
        $ftSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A-'.bin2hex(random_bytes(4)));

        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => 'FT',
            'payload' => [
                'series_id' => $ftSeriesId,
                'pricing_mode' => 'net',
                'rounding_method' => 'per_line',
                'date' => '2026-01-01',
                'lines' => [
                    // ISE/M99 (exempt): gross_total equals unit_price exactly,
                    // so this test's amounts don't have to account for VAT.
                    ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => $unitPrice, 'tax_region' => 'PT', 'tax_code' => 'ISE', 'exemption_reason_code' => 'M99'],
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

    /**
     * @return array{atcud: string, status: string, total: string}
     */
    private function fetchReceipt(string $companyId, string $receiptId): array
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));
        /** @var array{atcud: string, status: string, total: string} $row */
        $row = $connection->fetchAssociative('SELECT atcud, status, total FROM receipts WHERE company_id = ? AND id = ?', [$companyId, $receiptId]);

        return $row;
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

        $user = User::register(UserId::generate(), $email, 'Issue Receipt Flow User', $hasher->hash($password), new \DateTimeImmutable());
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
        return \sprintf('issue-receipt-%s@example.test', bin2hex(random_bytes(8)));
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
