<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `warehouses` (technical-scope.md §6.10) — company-scoped, with Row-Level
 * Security, docs/plans/phase-1.md task 1.9. `audit_log`/`idempotency_keys`
 * (raw-DBAL tables) are the recurring doctrine:migrations:diff false
 * positive from every earlier task; stripped by hand.
 */
final class Version20260912002951 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'warehouses, with Row-Level Security';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE warehouses (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              code VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              address VARCHAR(255) DEFAULT NULL,
              is_default BOOLEAN NOT NULL,
              active BOOLEAN NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_warehouses_company_code ON warehouses (company_id, code)');
        $this->enableCompanyIsolation('warehouses');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE warehouses');
    }
}
