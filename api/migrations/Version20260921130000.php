<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * docs/plans/phase-2.md task 2.6:
 *
 * - The §6.9 series-issuance invariant ("last_number can only increase by
 *   exactly one per issuance, and last_issue_date/last_system_entry_at can
 *   never move backwards") as a DB trigger — {@see \App\Fiscal\Domain\Series::recordIssuance()}
 *   already enforces this in application code under the row's pessimistic
 *   lock, but CLAUDE.md's immutability rules are enforced at the database
 *   level throughout this schema, not left to application code alone; this
 *   is the same defense-in-depth task 2.3 already applied to `documents`/
 *   `receipts`. Only fires when one of the three columns actually changes,
 *   so `series`' ordinary CRUD/lifecycle updates (code, status,
 *   validation_code, …) are untouched.
 * - `at_communications` (§6.12/§7.5): the AT outbox. Deliberately *not*
 *   insert-only (unlike task 2.3's fiscal-document family) — retries
 *   mutate status/attempts/next_attempt_at freely, per §6.9's own table
 *   list, which omits this one.
 */
final class Version20260921130000 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'series issuance-invariant trigger, at_communications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE FUNCTION restrict_series_issuance_columns() RETURNS trigger AS $$
            BEGIN
              IF NEW.last_number IS DISTINCT FROM OLD.last_number
                 OR NEW.last_hash IS DISTINCT FROM OLD.last_hash
                 OR NEW.last_issue_date IS DISTINCT FROM OLD.last_issue_date
                 OR NEW.last_system_entry_at IS DISTINCT FROM OLD.last_system_entry_at THEN

                IF NEW.last_number <> COALESCE(OLD.last_number, OLD.first_number - 1) + 1 THEN
                  RAISE EXCEPTION 'series.last_number must increase by exactly one per issuance';
                END IF;

                IF OLD.last_issue_date IS NOT NULL AND NEW.last_issue_date < OLD.last_issue_date THEN
                  RAISE EXCEPTION 'series.last_issue_date cannot move backwards';
                END IF;

                IF OLD.last_system_entry_at IS NOT NULL AND NEW.last_system_entry_at < OLD.last_system_entry_at THEN
                  RAISE EXCEPTION 'series.last_system_entry_at cannot move backwards';
                END IF;
              END IF;

              RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        $this->addSql('CREATE TRIGGER series_restrict_issuance_columns BEFORE UPDATE ON series FOR EACH ROW EXECUTE FUNCTION restrict_series_issuance_columns()');

        $this->addSql(<<<'SQL'
            CREATE TABLE at_communications (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              kind VARCHAR(255) NOT NULL,
              subject_type VARCHAR(255) NOT NULL,
              subject_id VARCHAR(255) NOT NULL,
              status VARCHAR(255) NOT NULL,
              attempts INT NOT NULL,
              next_attempt_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              request_digest VARCHAR(255) DEFAULT NULL,
              response_code VARCHAR(255) DEFAULT NULL,
              response_message TEXT DEFAULT NULL,
              at_reference VARCHAR(255) DEFAULT NULL,
              created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_at_communications_company_subject ON at_communications (company_id, subject_type, subject_id)');
        $this->addSql('CREATE INDEX idx_at_communications_status_next_attempt ON at_communications (status, next_attempt_at)');
        $this->enableCompanyIsolation('at_communications');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE at_communications');
        $this->addSql('DROP TRIGGER series_restrict_issuance_columns ON series');
        $this->addSql('DROP FUNCTION restrict_series_issuance_columns()');
    }
}
