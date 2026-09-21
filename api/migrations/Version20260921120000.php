<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `document_drafts` (technical-scope.md §6.6) — company-scoped, with Row-
 * Level Security, docs/plans/phase-2.md task 2.4. Mutable, ordinary CRUD
 * grants (unlike task 2.3's fiscal-document family): CLAUDE.md — "Drafts
 * are not documents".
 */
final class Version20260921120000 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'document_drafts, with Row-Level Security';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE document_drafts (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              document_type VARCHAR(255) NOT NULL,
              series_id VARCHAR(255) DEFAULT NULL,
              customer_id VARCHAR(255) DEFAULT NULL,
              payload JSONB NOT NULL,
              calculated JSONB DEFAULT NULL,
              created_by VARCHAR(255) NOT NULL,
              updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_document_drafts_company_updated ON document_drafts (company_id, updated_at)');
        $this->enableCompanyIsolation('document_drafts');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE document_drafts');
    }
}
