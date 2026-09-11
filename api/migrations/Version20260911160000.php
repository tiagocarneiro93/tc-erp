<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backs `Idempotency-Key` support (technical-scope.md §9.1), ready ahead of
 * Phase 2's issuing endpoints (CLAUDE.md task 0.11). Company-scoped like
 * every other table that isn't explicitly listed as global (§5.1).
 */
final class Version20260911160000 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'idempotency_keys: stored responses for Idempotency-Key retries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE idempotency_keys (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              idempotency_key VARCHAR(255) NOT NULL,
              request_hash VARCHAR(64) NOT NULL,
              response_status SMALLINT NOT NULL,
              response_body TEXT NOT NULL,
              created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_idempotency_keys_company_key ON idempotency_keys (company_id, idempotency_key)');

        $this->enableCompanyIsolation('idempotency_keys');

        // Deliberately not made insert-only (unlike audit_log): a future
        // cleanup sweeper will need to DELETE expired rows. Nothing needs
        // UPDATE, but nothing relies on it being impossible either.
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE idempotency_keys');
    }
}
