<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * First company-scoped table (CLAUDE.md task 0.9, technical-scope.md §6.12,
 * §5.2): insert-only audit log, isolated by the standard RLS policy plus an
 * explicit revoke, since app_owner's default privileges
 * (docker/postgres/init/02-databases.sql) grant app_runtime UPDATE/DELETE on
 * every table unless revoked per table.
 */
final class Version20260911150000 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'audit_log: first company-scoped table, with Row-Level Security';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_log (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              occurred_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              user_id UUID DEFAULT NULL,
              api_token_id UUID DEFAULT NULL,
              action VARCHAR(255) NOT NULL,
              subject_type VARCHAR(255) NOT NULL,
              subject_id VARCHAR(255) NOT NULL,
              data JSONB NOT NULL,
              ip VARCHAR(45) NOT NULL,
              user_agent VARCHAR(1024) NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_audit_log_company_id ON audit_log (company_id)');
        $this->addSql('CREATE INDEX IDX_audit_log_subject ON audit_log (company_id, subject_type, subject_id)');

        $this->enableCompanyIsolation('audit_log');
        $this->makeInsertOnly('audit_log');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
    }
}
