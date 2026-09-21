<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The fiscal-document family (technical-scope.md §6.6/§6.9), docs/plans/phase-2.md
 * task 2.3: `documents`, `document_lines`, `document_tax_summary`,
 * `document_references`, `document_status_events`, `receipts`,
 * `receipt_allocations` — every table task 2.3 names, created and locked
 * down together, before any application code writes to them (tasks 2.4,
 * 2.6-2.10 add the Doctrine entities/handlers). Mirrors how task 2.2 built
 * `series`' full §6.6 schema ahead of the code that would exercise every
 * column — here more so, since immutability has to be there from the first
 * row, not bolted on after real data exists (CLAUDE.md: "never grant the
 * runtime role more privileges to work around it").
 *
 * `movement_details`/`stock_movements`/`audit_log` are in §6.9's own table
 * list but out of task 2.3's explicit bullet (movement_details belongs
 * with GT/GR/GD, not built yet; stock_movements is Phase 5; audit_log
 * already has its own grants from Phase 0) — not created here.
 *
 * Money columns use NUMERIC(19,2), matching the established `money`
 * Doctrine type (`share_capital`, task 1.4) rather than §6.6's inline
 * "NUMERIC(18,2)" comment for document totals: nothing in §14's decisions
 * calls for a different money precision for documents specifically, and
 * every document total will need to be the same `Money` value object used
 * everywhere else once task 2.6 wires up the entity — the "18" reads as an
 * illustrative slip in the informal schema sketch, not a deliberate
 * decision, so this migration follows the type that's actually shipped.
 * Line-level amounts (net/gross/tax/discount/settlement on document_lines)
 * use NUMERIC(19,6), matching `Tax\Domain\CalculatedLine`'s output scale
 * (technical-scope.md §7.9.3, `PriceCalculator::LINE_SCALE`) — this is
 * exactly what task 2.6's canonical calculation step will persist here.
 * Percentages use NUMERIC(5,2), matching the `percentage` Doctrine type.
 *
 * `receipts` gets `status_at`/`status_reason` columns even though §6.6's
 * receipts sketch didn't list them: §6.9 explicitly says "UPDATE on
 * documents/receipts is allowed only through a trigger that rejects any
 * change except to status, status_at, status_reason" — naming the same
 * three columns for both tables — so this migration adds the two missing
 * ones to `receipts` to make that already-stated rule enforceable, rather
 * than re-deciding what's mutable on receipts from scratch.
 *
 * Every cross-module reference (customer_id, product_id on document_lines,
 * source_id/user_id) is a plain UUID/string column with no DB-level FOREIGN
 * KEY, matching every other cross-module reference in this codebase
 * (tax_rate_id, payment_terms_id, …) — validated at the application layer,
 * never via a DB constraint that would require Fiscal's migration to know
 * about Parties'/Catalog's/Platform's tables.
 */
final class Version20260921110000 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'documents, document_lines, document_tax_summary, document_references, document_status_events, receipts, receipt_allocations — insert-only, with Row-Level Security';
    }

    public function up(Schema $schema): void
    {
        $this->createDocuments();
        $this->createDocumentLines();
        $this->createDocumentTaxSummary();
        $this->createDocumentReferences();
        $this->createDocumentStatusEvents();
        $this->createReceipts();
        $this->createReceiptAllocations();
    }

    private function createDocuments(): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE documents (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              document_type VARCHAR(255) NOT NULL,
              series_id UUID NOT NULL,
              number INT NOT NULL,
              document_no VARCHAR(255) NOT NULL,
              atcud VARCHAR(255) NOT NULL,
              issue_date TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              system_entry_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              customer_id UUID DEFAULT NULL,
              customer_snapshot JSONB NOT NULL,
              issuer_snapshot JSONB NOT NULL,
              template_version VARCHAR(255) NOT NULL,
              pricing_mode VARCHAR(255) NOT NULL,
              rounding_method VARCHAR(255) NOT NULL,
              currency CHAR(3) NOT NULL,
              exchange_rate NUMERIC(19, 6) DEFAULT NULL,
              global_discount_percent NUMERIC(5, 2) DEFAULT NULL,
              settlement_total NUMERIC(19, 2) NOT NULL,
              net_total NUMERIC(19, 2) NOT NULL,
              tax_total NUMERIC(19, 2) NOT NULL,
              gross_total NUMERIC(19, 2) NOT NULL,
              withholding_total NUMERIC(19, 2) DEFAULT NULL,
              payment_terms JSONB DEFAULT NULL,
              due_date TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              hash TEXT NOT NULL,
              hash_control VARCHAR(255) NOT NULL,
              qr_payload TEXT NOT NULL,
              is_training BOOLEAN NOT NULL,
              status CHAR(1) NOT NULL,
              status_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              status_reason TEXT DEFAULT NULL,
              source_id VARCHAR(255) NOT NULL,
              issued_via VARCHAR(255) NOT NULL,
              idempotency_key VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_documents_company_series_number ON documents (company_id, series_id, number)');
        $this->addSql('CREATE UNIQUE INDEX uniq_documents_company_idempotency_key ON documents (company_id, idempotency_key)');
        $this->addSql('CREATE INDEX idx_documents_company_customer ON documents (company_id, customer_id)');
        $this->enableCompanyIsolation('documents');

        // §6.9: INSERT/SELECT only, plus a trigger-restricted UPDATE for
        // status/status_at/status_reason — never a blanket makeInsertOnly().
        $this->addSql('REVOKE DELETE ON documents FROM app_runtime');
        $this->addSql(<<<'SQL'
            CREATE FUNCTION restrict_documents_update() RETURNS trigger AS $$
            BEGIN
              IF (to_jsonb(NEW) - ARRAY['status', 'status_at', 'status_reason']::text[])
                 <> (to_jsonb(OLD) - ARRAY['status', 'status_at', 'status_reason']::text[]) THEN
                RAISE EXCEPTION 'documents rows are immutable except status, status_at and status_reason';
              END IF;

              IF NEW.status <> OLD.status AND NOT EXISTS (
                SELECT 1 FROM document_status_events
                WHERE company_id = NEW.company_id AND document_id = NEW.id AND status = NEW.status
              ) THEN
                RAISE EXCEPTION 'a status change on documents must have a matching document_status_events row';
              END IF;

              RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        $this->addSql('CREATE TRIGGER documents_restrict_update BEFORE UPDATE ON documents FOR EACH ROW EXECUTE FUNCTION restrict_documents_update()');
    }

    private function createDocumentLines(): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE document_lines (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              document_id UUID NOT NULL,
              line_number INT NOT NULL,
              product_id UUID DEFAULT NULL,
              product_code VARCHAR(255) NOT NULL,
              product_description TEXT NOT NULL,
              product_type VARCHAR(1) NOT NULL,
              unit_code VARCHAR(255) NOT NULL,
              quantity NUMERIC(19, 6) NOT NULL,
              unit_price NUMERIC(19, 6) NOT NULL,
              discount_percent NUMERIC(5, 2) DEFAULT NULL,
              discount_amount NUMERIC(19, 6) NOT NULL,
              settlement_amount NUMERIC(19, 6) NOT NULL,
              net_amount NUMERIC(19, 6) NOT NULL,
              gross_amount NUMERIC(19, 6) NOT NULL,
              tax_region VARCHAR(255) NOT NULL,
              tax_code VARCHAR(255) NOT NULL,
              tax_percentage NUMERIC(5, 2) NOT NULL,
              tax_amount NUMERIC(19, 6) NOT NULL,
              exemption_reason_code VARCHAR(255) DEFAULT NULL,
              exemption_reason_text TEXT DEFAULT NULL,
              tax_point_date TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              origin_references JSONB DEFAULT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_document_lines_company_document_line_number ON document_lines (company_id, document_id, line_number)');
        $this->addSql('CREATE INDEX idx_document_lines_company_product ON document_lines (company_id, product_id)');
        $this->enableCompanyIsolation('document_lines');
        $this->makeInsertOnly('document_lines');
    }

    private function createDocumentTaxSummary(): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE document_tax_summary (
              company_id UUID NOT NULL,
              document_id UUID NOT NULL,
              tax_region VARCHAR(255) NOT NULL,
              tax_code VARCHAR(255) NOT NULL,
              tax_percentage NUMERIC(5, 2) NOT NULL,
              taxable_base NUMERIC(19, 2) NOT NULL,
              tax_amount NUMERIC(19, 2) NOT NULL,
              PRIMARY KEY (company_id, document_id, tax_region, tax_code)
            )
            SQL);
        $this->enableCompanyIsolation('document_tax_summary');
        $this->makeInsertOnly('document_tax_summary');
    }

    private function createDocumentReferences(): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE document_references (
              company_id UUID NOT NULL,
              document_id UUID NOT NULL,
              referenced_document_no VARCHAR(255) NOT NULL,
              reason TEXT DEFAULT NULL,
              PRIMARY KEY (company_id, document_id, referenced_document_no)
            )
            SQL);
        $this->enableCompanyIsolation('document_references');
        $this->makeInsertOnly('document_references');
    }

    private function createDocumentStatusEvents(): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE document_status_events (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              document_id UUID NOT NULL,
              status CHAR(1) NOT NULL,
              reason TEXT DEFAULT NULL,
              user_id VARCHAR(255) DEFAULT NULL,
              occurred_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_document_status_events_company_document ON document_status_events (company_id, document_id)');
        $this->enableCompanyIsolation('document_status_events');
        $this->makeInsertOnly('document_status_events');
    }

    private function createReceipts(): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE receipts (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              series_id UUID NOT NULL,
              number INT NOT NULL,
              document_no VARCHAR(255) NOT NULL,
              atcud VARCHAR(255) NOT NULL,
              issue_date TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              system_entry_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              customer_id UUID DEFAULT NULL,
              customer_snapshot JSONB NOT NULL,
              total NUMERIC(19, 2) NOT NULL,
              payment_method VARCHAR(255) NOT NULL,
              status CHAR(1) NOT NULL,
              status_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              status_reason TEXT DEFAULT NULL,
              source_id VARCHAR(255) NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_receipts_company_series_number ON receipts (company_id, series_id, number)');
        $this->enableCompanyIsolation('receipts');

        $this->addSql('REVOKE DELETE ON receipts FROM app_runtime');
        $this->addSql(<<<'SQL'
            CREATE FUNCTION restrict_receipts_update() RETURNS trigger AS $$
            BEGIN
              IF (to_jsonb(NEW) - ARRAY['status', 'status_at', 'status_reason']::text[])
                 <> (to_jsonb(OLD) - ARRAY['status', 'status_at', 'status_reason']::text[]) THEN
                RAISE EXCEPTION 'receipts rows are immutable except status, status_at and status_reason';
              END IF;

              RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        $this->addSql('CREATE TRIGGER receipts_restrict_update BEFORE UPDATE ON receipts FOR EACH ROW EXECUTE FUNCTION restrict_receipts_update()');
    }

    private function createReceiptAllocations(): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE receipt_allocations (
              company_id UUID NOT NULL,
              receipt_id UUID NOT NULL,
              document_id UUID NOT NULL,
              amount NUMERIC(19, 2) NOT NULL,
              settlement_amount NUMERIC(19, 2) NOT NULL,
              PRIMARY KEY (company_id, receipt_id, document_id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_receipt_allocations_company_document ON receipt_allocations (company_id, document_id)');
        $this->enableCompanyIsolation('receipt_allocations');
        $this->makeInsertOnly('receipt_allocations');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE receipt_allocations');
        $this->addSql('DROP TRIGGER receipts_restrict_update ON receipts');
        $this->addSql('DROP FUNCTION restrict_receipts_update()');
        $this->addSql('DROP TABLE receipts');
        $this->addSql('DROP TABLE document_status_events');
        $this->addSql('DROP TABLE document_references');
        $this->addSql('DROP TABLE document_tax_summary');
        $this->addSql('DROP TABLE document_lines');
        $this->addSql('DROP TRIGGER documents_restrict_update ON documents');
        $this->addSql('DROP FUNCTION restrict_documents_update()');
        $this->addSql('DROP TABLE documents');
    }
}
