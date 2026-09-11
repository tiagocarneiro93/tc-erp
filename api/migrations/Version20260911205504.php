<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Global reference data (technical-scope.md §6.1/§6.4), no RLS — see
 * docs/plans/phase-1.md task 1.1. `audit_log`/`idempotency_keys` are not
 * Doctrine entities (written via raw DBAL), so doctrine:migrations:diff
 * proposed dropping them as a false positive; stripped from this migration.
 */
final class Version20260911205504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Global reference data: countries, units';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE countries (
              code VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              PRIMARY KEY (code)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE units (
              code VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              decimals INT NOT NULL,
              PRIMARY KEY (code)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE countries');
        $this->addSql('DROP TABLE units');
    }
}
