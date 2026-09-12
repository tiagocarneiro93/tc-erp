<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replaces `customers.payment_terms_days`/`suppliers.payment_terms_days`
 * (a free-typed integer, task 1.5) with `payment_terms_id`, a FK into the
 * new company-managed `payment_terms` catalog (Version20260912120000).
 * No production data exists yet for either table (Phase 1 master data,
 * pre-launch), so this is a clean cutover rather than a migrate-then-drop
 * two-step.
 */
final class Version20260912121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'customers/suppliers: payment_terms_days -> payment_terms_id';
    }

    public function up(Schema $schema): void
    {
        // VARCHAR, not UUID, matching the same cross-module plain-string FK
        // convention as products.tax_rate_id/exemption_reason_code: Parties
        // stores the id as an opaque string (Deptrac forbids depending on
        // Company\Domain\PaymentTermsId directly), so there's no typed
        // Doctrine column mapping to a native UUID column here either.
        $this->addSql('ALTER TABLE customers DROP COLUMN payment_terms_days');
        $this->addSql('ALTER TABLE customers ADD payment_terms_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE suppliers DROP COLUMN payment_terms_days');
        $this->addSql('ALTER TABLE suppliers ADD payment_terms_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customers DROP COLUMN payment_terms_id');
        $this->addSql('ALTER TABLE customers ADD payment_terms_days INT DEFAULT NULL');
        $this->addSql('ALTER TABLE suppliers DROP COLUMN payment_terms_id');
        $this->addSql('ALTER TABLE suppliers ADD payment_terms_days INT DEFAULT NULL');
    }
}
