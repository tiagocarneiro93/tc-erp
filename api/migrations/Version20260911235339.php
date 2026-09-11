<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `product_families`, `products` (technical-scope.md §6.4) —
 * company-scoped, with Row-Level Security, docs/plans/phase-1.md task 1.6.
 * `audit_log`/`idempotency_keys` (raw-DBAL tables) are the recurring
 * doctrine:migrations:diff false positive from every earlier task;
 * stripped by hand.
 */
final class Version20260911235339 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'product_families, products, with Row-Level Security';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE product_families (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              name VARCHAR(255) NOT NULL,
              parent_id UUID DEFAULT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->enableCompanyIsolation('product_families');

        $this->addSql(<<<'SQL'
            CREATE TABLE products (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              code VARCHAR(255) NOT NULL,
              description VARCHAR(255) NOT NULL,
              type VARCHAR(255) NOT NULL,
              kind VARCHAR(255) NOT NULL,
              unit_code VARCHAR(255) NOT NULL,
              barcode VARCHAR(255) DEFAULT NULL,
              family_id UUID DEFAULT NULL,
              tax_rate_id VARCHAR(255) NOT NULL,
              exemption_reason_code VARCHAR(255) DEFAULT NULL,
              track_stock BOOLEAN NOT NULL,
              active BOOLEAN NOT NULL,
              last_cost NUMERIC(19, 6) DEFAULT NULL,
              average_cost NUMERIC(19, 6) DEFAULT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_products_company_code ON products (company_id, code)');
        $this->addSql('CREATE INDEX idx_products_company_family ON products (company_id, family_id)');
        $this->enableCompanyIsolation('products');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_families');
        $this->addSql('DROP TABLE products');
    }
}
