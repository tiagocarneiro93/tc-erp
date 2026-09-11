<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `company_profile`, `settings`, `at_credentials` (technical-scope.md §6.2)
 * — company-scoped, with Row-Level Security, docs/plans/phase-1.md task
 * 1.4. `audit_log`/`idempotency_keys` (raw-DBAL tables) are the recurring
 * doctrine:migrations:diff false positive from every earlier task; stripped
 * by hand.
 */
final class Version20260911214724 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'company_profile, settings, at_credentials, with Row-Level Security';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE company_profile (
              company_id UUID NOT NULL,
              nif CHAR(9) NOT NULL,
              legal_name VARCHAR(255) NOT NULL,
              commercial_name VARCHAR(255) DEFAULT NULL,
              address VARCHAR(255) DEFAULT NULL,
              postal_code VARCHAR(255) DEFAULT NULL,
              city VARCHAR(255) DEFAULT NULL,
              country VARCHAR(255) NOT NULL,
              share_capital NUMERIC(19, 2) DEFAULT NULL,
              registry_office VARCHAR(255) DEFAULT NULL,
              email VARCHAR(255) DEFAULT NULL,
              phone VARCHAR(255) DEFAULT NULL,
              logo_key VARCHAR(255) DEFAULT NULL,
              fiscal_region VARCHAR(255) NOT NULL,
              vat_regime VARCHAR(255) NOT NULL,
              cash_vat BOOLEAN NOT NULL,
              PRIMARY KEY (company_id)
            )
            SQL);
        $this->enableCompanyIsolation('company_profile');

        $this->addSql(<<<'SQL'
            CREATE TABLE settings (
              company_id UUID NOT NULL,
              key VARCHAR(255) NOT NULL,
              value JSONB NOT NULL,
              PRIMARY KEY (company_id, key)
            )
            SQL);
        $this->enableCompanyIsolation('settings');

        $this->addSql(<<<'SQL'
            CREATE TABLE at_credentials (
              company_id UUID NOT NULL,
              subuser VARCHAR(255) NOT NULL,
              password_encrypted TEXT NOT NULL,
              validated_at TIMESTAMPTZ DEFAULT NULL,
              last_error VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY (company_id)
            )
            SQL);
        $this->enableCompanyIsolation('at_credentials');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE company_profile');
        $this->addSql('DROP TABLE settings');
        $this->addSql('DROP TABLE at_credentials');
    }
}
