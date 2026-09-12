<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `price_lists`, `product_prices` (technical-scope.md §6.4/§7.9.8) —
 * company-scoped, with Row-Level Security, docs/plans/phase-1.md task 1.7.
 * `product_prices` has no surrogate id: its primary key is exactly
 * `(company_id, product_id, price_list_id)`, as the scope specifies.
 * `audit_log`/`idempotency_keys` (raw-DBAL tables) are the recurring
 * doctrine:migrations:diff false positive from every earlier task;
 * stripped by hand.
 */
final class Version20260912000701 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'price_lists, product_prices, with Row-Level Security';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE price_lists (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              name VARCHAR(255) NOT NULL,
              default_includes_vat BOOLEAN NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->enableCompanyIsolation('price_lists');

        $this->addSql(<<<'SQL'
            CREATE TABLE product_prices (
              company_id UUID NOT NULL,
              product_id UUID NOT NULL,
              price_list_id UUID NOT NULL,
              amount NUMERIC(19, 6) NOT NULL,
              includes_vat BOOLEAN NOT NULL,
              PRIMARY KEY (company_id, product_id, price_list_id)
            )
            SQL);
        $this->enableCompanyIsolation('product_prices');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE price_lists');
        $this->addSql('DROP TABLE product_prices');
    }
}
