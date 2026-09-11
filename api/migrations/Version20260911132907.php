<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Password reset tokens (task 0.8) — single-use, time-limited links mailed
 * to the user; only the hash of the token is stored.
 */
final class Version20260911132907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Password reset tokens table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE password_reset_tokens (
              id UUID NOT NULL,
              user_id UUID NOT NULL,
              token_hash VARCHAR(255) NOT NULL,
              expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              used_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3967A216B3BC57DA ON password_reset_tokens (token_hash)');
        $this->addSql('CREATE INDEX IDX_3967A216A76ED395 ON password_reset_tokens (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_reset_tokens');
    }
}
