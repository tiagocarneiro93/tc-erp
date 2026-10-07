<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * docs/plans/phase-3.md task 3.4 / technical-scope.md §6.12: `stored_files`,
 * the register of artifacts kept in object storage (sealed PDFs, SAF-T
 * exports, attachments). Insert-only like every other record of something
 * issued or archived (CLAUDE.md: issued fiscal data is immutable) — a stored
 * artifact is never rewritten, only ever added; the bytes it points at are
 * additionally protected by `sha256`, checked on every read.
 *
 * `kind` is restricted in the database itself, not just in PHP: §6.12's own
 * enum, enforced where nothing can bypass it.
 */
final class Version20261007100000 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'stored_files (insert-only, RLS)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE stored_files (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              kind VARCHAR(255) NOT NULL,
              subject_type VARCHAR(255) NOT NULL,
              subject_id VARCHAR(255) NOT NULL,
              storage_key VARCHAR(512) NOT NULL,
              sha256 CHAR(64) NOT NULL,
              size BIGINT NOT NULL,
              created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT stored_files_kind_check CHECK (kind IN ('sealed_pdf', 'saft', 'attachment')),
              CONSTRAINT stored_files_sha256_check CHECK (sha256 ~ '^[0-9a-f]{64}$'),
              CONSTRAINT stored_files_size_check CHECK (size >= 0)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_stored_files_company_storage_key ON stored_files (company_id, storage_key)');
        $this->addSql('CREATE INDEX idx_stored_files_company_subject ON stored_files (company_id, kind, subject_type, subject_id)');
        $this->enableCompanyIsolation('stored_files');
        $this->makeInsertOnly('stored_files');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stored_files');
    }
}
