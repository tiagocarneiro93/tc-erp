<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * docs/plans/phase-3.md task 3.5 / technical-scope.md §6.12 and §7.8:
 * `document_prints` — every print, download and e-mail of an issued document,
 * insert-only. It drives the "original / copy" marking (Despacho 8632/2014
 * §2.2.15: a second copy must carry an expression showing it is not the
 * original) and is itself an audit trail of who handed a fiscal document out.
 */
final class Version20261007110000 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'document_prints (insert-only, RLS)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE document_prints (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              document_id UUID NOT NULL,
              kind VARCHAR(255) NOT NULL,
              copy_label VARCHAR(255) NOT NULL,
              user_id VARCHAR(255) NOT NULL,
              occurred_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT document_prints_kind_check CHECK (kind IN ('print', 'download', 'email'))
            )
            SQL);
        $this->addSql('CREATE INDEX idx_document_prints_company_document ON document_prints (company_id, document_id, occurred_at)');
        $this->enableCompanyIsolation('document_prints');
        $this->makeInsertOnly('document_prints');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE document_prints');
    }
}
