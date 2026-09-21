<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fiscal;

use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * technical-scope.md §6.9, docs/plans/phase-2.md task 2.3: the DB role
 * (`app_runtime`) enforces immutability directly, not just application
 * code — CLAUDE.md's "never grant the runtime role more privileges to
 * work around it". Runs every statement as `app_runtime` (the connection
 * this test suite already uses), inside a rolled-back transaction so
 * nothing persists.
 */
final class FiscalTableImmutabilityTest extends KernelTestCase
{
    private Connection $connection;
    private RequestCompanyContext $companyContext;
    private CompanyId $companyId;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
        /** @var RequestCompanyContext $context */
        $context = self::getContainer()->get(RequestCompanyContext::class);
        $this->companyContext = $context;

        $this->companyId = CompanyId::generate();
        $this->companyContext->set($this->companyId);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->companyContext->clear();

        parent::tearDown();
    }

    public function testDeleteIsRejectedOnEveryFiscalTable(): void
    {
        $documentId = $this->insertDocument();

        $this->assertPermissionDenied(fn () => $this->connection->executeStatement(
            'DELETE FROM documents WHERE id = :id',
            ['id' => $documentId],
        ), 'documents');

        $this->assertPermissionDenied(fn () => $this->connection->executeStatement(
            'DELETE FROM document_lines WHERE document_id = :id',
            ['id' => $documentId],
        ), 'document_lines');

        $this->assertPermissionDenied(fn () => $this->connection->executeStatement(
            'DELETE FROM document_tax_summary WHERE document_id = :id',
            ['id' => $documentId],
        ), 'document_tax_summary');

        $this->assertPermissionDenied(fn () => $this->connection->executeStatement(
            'DELETE FROM document_status_events WHERE document_id = :id',
            ['id' => $documentId],
        ), 'document_status_events');

        $receiptId = $this->insertReceipt();

        $this->assertPermissionDenied(fn () => $this->connection->executeStatement(
            'DELETE FROM receipts WHERE id = :id',
            ['id' => $receiptId],
        ), 'receipts');
    }

    public function testUpdateIsRejectedOutrightOnPureInsertOnlyTables(): void
    {
        $documentId = $this->insertDocument();
        $this->insertDocumentLine($documentId);

        $this->assertPermissionDenied(fn () => $this->connection->executeStatement(
            'UPDATE document_lines SET net_amount = 1 WHERE document_id = :id',
            ['id' => $documentId],
        ), 'document_lines');

        $this->assertPermissionDenied(fn () => $this->connection->executeStatement(
            'UPDATE document_tax_summary SET tax_amount = 1 WHERE document_id = :id',
            ['id' => $documentId],
        ), 'document_tax_summary');

        $this->assertPermissionDenied(fn () => $this->connection->executeStatement(
            'UPDATE document_status_events SET reason = :r WHERE document_id = :id',
            ['id' => $documentId, 'r' => 'x'],
        ), 'document_status_events');
    }

    public function testDocumentsAllowsOnlyStatusColumnsAndOnlyWithAMatchingEvent(): void
    {
        $documentId = $this->insertDocument();

        $this->assertRaises(
            fn () => $this->connection->executeStatement(
                'UPDATE documents SET gross_total = 999 WHERE id = :id',
                ['id' => $documentId],
            ),
            'immutable except status',
        );

        $this->assertRaises(
            fn () => $this->connection->executeStatement(
                "UPDATE documents SET status = 'A' WHERE id = :id",
                ['id' => $documentId],
            ),
            'matching document_status_events row',
        );
    }

    public function testDocumentsAllowsTheStatusColumnsOnceAMatchingEventExists(): void
    {
        $documentId = $this->insertDocument();
        $this->connection->executeStatement(
            "INSERT INTO document_status_events (id, company_id, document_id, status, occurred_at) VALUES (:id, :companyId, :documentId, 'A', now())",
            ['id' => self::uuid(), 'companyId' => $this->companyId->toString(), 'documentId' => $documentId],
        );

        $this->connection->executeStatement(
            "UPDATE documents SET status = 'A', status_reason = 'cancelled' WHERE id = :id",
            ['id' => $documentId],
        );

        $status = $this->connection->fetchOne('SELECT status FROM documents WHERE id = :id', ['id' => $documentId]);
        self::assertSame('A', $status);
    }

    public function testReceiptsAllowsOnlyStatusColumns(): void
    {
        $receiptId = $this->insertReceipt();

        $this->assertRaises(
            fn () => $this->connection->executeStatement(
                'UPDATE receipts SET total = 999 WHERE id = :id',
                ['id' => $receiptId],
            ),
            'immutable except status',
        );

        $this->connection->executeStatement(
            "UPDATE receipts SET status = 'A' WHERE id = :id",
            ['id' => $receiptId],
        );
        $status = $this->connection->fetchOne('SELECT status FROM receipts WHERE id = :id', ['id' => $receiptId]);
        self::assertSame('A', $status);
    }

    /**
     * @param callable(): mixed $action
     */
    private function assertPermissionDenied(callable $action, string $table): void
    {
        $this->assertRaises($action, 'permission denied', $table);
    }

    /**
     * Wraps $action in its own SAVEPOINT: a failing statement aborts only
     * that savepoint, not the whole outer (per-test) transaction, so
     * fixture rows inserted earlier in the same test survive to be reused
     * by later assertions.
     *
     * @param callable(): mixed $action
     */
    private function assertRaises(callable $action, string $expectedMessageFragment, ?string $label = null): void
    {
        $this->connection->executeStatement('SAVEPOINT assertion');

        try {
            $action();
            self::fail(\sprintf('Expected an exception containing "%s"%s.', $expectedMessageFragment, null !== $label ? " ($label)" : ''));
        } catch (DbalException $e) {
            self::assertStringContainsString($expectedMessageFragment, $e->getMessage());
            $this->connection->executeStatement('ROLLBACK TO SAVEPOINT assertion');
        }
    }

    private function insertDocument(): string
    {
        $id = self::uuid();
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO documents (
                  id, company_id, document_type, series_id, number, document_no, atcud,
                  issue_date, system_entry_at, customer_snapshot, issuer_snapshot,
                  template_version, pricing_mode, rounding_method, currency,
                  settlement_total, net_total, tax_total, gross_total,
                  hash, hash_control, qr_payload, is_training, status, status_at,
                  source_id, issued_via
                ) VALUES (
                  :id, :companyId, 'FT', :seriesId, 1, 'FT 2026A/1', 'ABC-1',
                  now(), now(), '{}', '{}',
                  'v1', 'net', 'per_line', 'EUR',
                  100.00, 100.00, 23.00, 123.00,
                  'somehash', 'v1', 'qrpayload', false, 'N', now(),
                  'user1', 'web'
                )
                SQL,
            ['id' => $id, 'companyId' => $this->companyId->toString(), 'seriesId' => self::uuid()],
        );

        return $id;
    }

    private function insertDocumentLine(string $documentId): string
    {
        $id = self::uuid();
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO document_lines (
                  id, company_id, document_id, line_number, product_code, product_description,
                  product_type, unit_code, quantity, unit_price, discount_amount,
                  settlement_amount, net_amount, gross_amount, tax_region, tax_code,
                  tax_percentage, tax_amount
                ) VALUES (
                  :id, :companyId, :documentId, 1, 'SKU1', 'Widget',
                  'P', 'UN', 1, 100, 0,
                  0, 100, 123, 'PT', 'NOR',
                  23, 23
                )
                SQL,
            ['id' => $id, 'companyId' => $this->companyId->toString(), 'documentId' => $documentId],
        );

        return $id;
    }

    private function insertReceipt(): string
    {
        $id = self::uuid();
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO receipts (
                  id, company_id, series_id, number, document_no, atcud, issue_date,
                  system_entry_at, customer_snapshot, total, payment_method, status,
                  status_at, source_id
                ) VALUES (
                  :id, :companyId, :seriesId, 1, 'RG 2026A/1', 'ABC-1', now(),
                  now(), '{}', 100.00, 'cash', 'N',
                  now(), 'user1'
                )
                SQL,
            ['id' => $id, 'companyId' => $this->companyId->toString(), 'seriesId' => self::uuid()],
        );

        return $id;
    }

    private static function uuid(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
