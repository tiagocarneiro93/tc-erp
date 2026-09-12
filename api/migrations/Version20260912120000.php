<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Persistence\Doctrine\Migration\CompanyIsolationMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `payment_terms` — company-scoped, with Row-Level Security. Follow-up to
 * task 1.5: customers/suppliers get a `payment_terms_id` FK into this
 * catalog instead of a free-typed number of days (owner-approved, session
 * feedback after task 1.10).
 */
final class Version20260912120000 extends AbstractMigration
{
    use CompanyIsolationMigration;

    public function getDescription(): string
    {
        return 'payment_terms, with Row-Level Security';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE payment_terms (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              name VARCHAR(255) NOT NULL,
              days INT NOT NULL,
              is_default BOOLEAN NOT NULL,
              active BOOLEAN NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_payment_terms_company_id ON payment_terms (company_id)');
        $this->enableCompanyIsolation('payment_terms');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payment_terms');
    }
}
