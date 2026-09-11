<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `customers`, `suppliers`, `addresses` (technical-scope.md §6.3) —
 * company-scoped, with Row-Level Security, docs/plans/phase-1.md task 1.5.
 * `audit_log`/`idempotency_keys` (raw-DBAL tables) are the recurring
 * doctrine:migrations:diff false positive from every earlier task;
 * stripped by hand.
 */
final class Version20260911221006 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'customers, suppliers, addresses, with Row-Level Security';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE customers (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              code VARCHAR(255) NOT NULL,
              nif VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              address VARCHAR(255) DEFAULT NULL,
              postal_code VARCHAR(255) DEFAULT NULL,
              city VARCHAR(255) DEFAULT NULL,
              country VARCHAR(255) NOT NULL,
              email VARCHAR(255) DEFAULT NULL,
              phone VARCHAR(255) DEFAULT NULL,
              payment_terms_days INT DEFAULT NULL,
              price_list_id VARCHAR(255) DEFAULT NULL,
              is_final_consumer BOOLEAN NOT NULL,
              active BOOLEAN NOT NULL,
              created_at TIMESTAMPTZ NOT NULL,
              updated_at TIMESTAMPTZ NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_customers_company_code ON customers (company_id, code)');
        $this->enableCompanyIsolation('customers');

        $this->addSql(<<<'SQL'
            CREATE TABLE suppliers (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              code VARCHAR(255) NOT NULL,
              nif VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              address VARCHAR(255) DEFAULT NULL,
              postal_code VARCHAR(255) DEFAULT NULL,
              city VARCHAR(255) DEFAULT NULL,
              country VARCHAR(255) NOT NULL,
              email VARCHAR(255) DEFAULT NULL,
              phone VARCHAR(255) DEFAULT NULL,
              payment_terms_days INT DEFAULT NULL,
              active BOOLEAN NOT NULL,
              created_at TIMESTAMPTZ NOT NULL,
              updated_at TIMESTAMPTZ NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_suppliers_company_code ON suppliers (company_id, code)');
        $this->enableCompanyIsolation('suppliers');

        $this->addSql(<<<'SQL'
            CREATE TABLE addresses (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              party_type VARCHAR(255) NOT NULL,
              party_id VARCHAR(255) NOT NULL,
              label VARCHAR(255) NOT NULL,
              address VARCHAR(255) NOT NULL,
              postal_code VARCHAR(255) DEFAULT NULL,
              city VARCHAR(255) DEFAULT NULL,
              country VARCHAR(255) NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_addresses_company_party ON addresses (company_id, party_type, party_id)');
        $this->enableCompanyIsolation('addresses');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customers');
        $this->addSql('DROP TABLE suppliers');
        $this->addSql('DROP TABLE addresses');
    }
}
