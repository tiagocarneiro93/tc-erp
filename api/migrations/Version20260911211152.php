<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tax reference data (technical-scope.md §6.5), no RLS — global data,
 * versioned by validity dates. docs/plans/phase-1.md task 1.2.
 * `audit_log`/`idempotency_keys` are not Doctrine entities (written via
 * raw DBAL), so doctrine:migrations:diff proposed dropping them as a
 * false positive; stripped from this migration.
 */
final class Version20260911211152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tax reference data: tax_rates, exemption_reasons';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE exemption_reasons (
              code VARCHAR(255) NOT NULL,
              description VARCHAR(255) NOT NULL,
              legal_reference TEXT NOT NULL,
              valid_from TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              valid_to TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              PRIMARY KEY (code)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE tax_rates (
              id UUID NOT NULL,
              region VARCHAR(255) NOT NULL,
              code VARCHAR(255) NOT NULL,
              percentage NUMERIC(5, 2) NOT NULL,
              valid_from TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              valid_to TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              description VARCHAR(255) NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        // Unique, not just an index: exactly one rate per (region, code)
        // effective on a given date -- also what the seed migration's
        // ON CONFLICT target relies on for idempotency.
        $this->addSql('CREATE UNIQUE INDEX uniq_tax_rates_region_code_valid_from ON tax_rates (region, code, valid_from)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE exemption_reasons');
        $this->addSql('DROP TABLE tax_rates');
    }
}
