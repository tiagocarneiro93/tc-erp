<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `series` (technical-scope.md §6.6) — company-scoped, with Row-Level
 * Security, docs/plans/phase-2.md task 2.2. `last_number`/`last_hash`/
 * `last_issue_date`/`last_system_entry_at` are part of this schema but
 * stay unwritten until the issuance use case (task 2.6) exists.
 */
final class Version20260921100000 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'series, with Row-Level Security';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE series (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              document_type VARCHAR(255) NOT NULL,
              code VARCHAR(255) NOT NULL,
              is_training BOOLEAN NOT NULL,
              validation_code VARCHAR(255) DEFAULT NULL,
              status VARCHAR(255) NOT NULL,
              first_number INT NOT NULL,
              last_number INT DEFAULT NULL,
              last_hash TEXT DEFAULT NULL,
              last_issue_date TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              last_system_entry_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              at_communicated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              at_finished_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_series_company_document_type_code ON series (company_id, document_type, code)');
        $this->enableCompanyIsolation('series');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE series');
    }
}
