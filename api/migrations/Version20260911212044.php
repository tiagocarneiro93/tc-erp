<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * document_types (technical-scope.md §6.6), global, no RLS, structural
 * data — docs/plans/phase-1.md task 1.3. `audit_log`/`idempotency_keys`
 * (raw-DBAL tables) and `tax_rates`' unique index (hand-written SQL, not
 * declared in the ORM mapping) are false positives from
 * doctrine:migrations:diff, same as tasks 1.1/1.2; stripped by hand.
 */
final class Version20260911212044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'document_types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE document_types (
              code VARCHAR(255) NOT NULL,
              saft_section VARCHAR(255) NOT NULL,
              signed BOOLEAN NOT NULL,
              stock_effect VARCHAR(255) NOT NULL,
              account_effect VARCHAR(255) NOT NULL,
              requires_at_prior_communication BOOLEAN NOT NULL,
              PRIMARY KEY (code)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE document_types');
    }
}
