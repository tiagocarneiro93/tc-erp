<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `product_components` (technical-scope.md §7.10.1) — company-scoped, with
 * Row-Level Security, docs/plans/phase-1.md task 1.8. No surrogate id: its
 * primary key is exactly `(company_id, kit_product_id, component_product_id)`.
 * `audit_log`/`idempotency_keys` (raw-DBAL tables) are the recurring
 * doctrine:migrations:diff false positive from every earlier task;
 * stripped by hand.
 */
final class Version20260912001544 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'product_components, with Row-Level Security';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE product_components (
              company_id UUID NOT NULL,
              kit_product_id UUID NOT NULL,
              component_product_id UUID NOT NULL,
              quantity NUMERIC(19, 6) NOT NULL,
              sort_order INT NOT NULL,
              PRIMARY KEY (company_id, kit_product_id, component_product_id)
            )
            SQL);
        $this->enableCompanyIsolation('product_components');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_components');
    }
}
