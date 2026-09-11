<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Global platform tables (technical-scope.md §6.1): users, companies,
 * memberships, roles, role_permissions, api_tokens and signing_keys.
 * No RLS: these are global, not company-scoped (§5.1). api_tokens has a
 * company_id column despite that — see App\Platform\Domain\ApiToken.
 */
final class Version20260911103706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Platform global tables: users, companies, memberships, roles, role_permissions, api_tokens, signing_keys';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
              id UUID NOT NULL,
              email VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              password_hash VARCHAR(255) NOT NULL,
              must_change_password BOOLEAN NOT NULL,
              password_changed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              mfa_secret VARCHAR(255) DEFAULT NULL,
              status VARCHAR(255) NOT NULL,
              created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE companies (
              id UUID NOT NULL,
              nif CHAR(9) NOT NULL,
              legal_name VARCHAR(255) NOT NULL,
              status VARCHAR(255) NOT NULL,
              plan VARCHAR(255) DEFAULT NULL,
              created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8244AA3AADE62BBB ON companies (nif)');

        $this->addSql(<<<'SQL'
            CREATE TABLE memberships (
              user_id UUID NOT NULL,
              company_id UUID NOT NULL,
              role VARCHAR(255) NOT NULL,
              status VARCHAR(255) NOT NULL,
              created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              PRIMARY KEY (user_id, company_id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE roles (
              code VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              PRIMARY KEY (code)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE role_permissions (
              role_code VARCHAR(255) NOT NULL,
              permission VARCHAR(255) NOT NULL,
              PRIMARY KEY (role_code, permission)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE api_tokens (
              id UUID NOT NULL,
              company_id UUID NOT NULL,
              name VARCHAR(255) NOT NULL,
              token_hash VARCHAR(255) NOT NULL,
              scopes JSONB NOT NULL,
              last_used_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE signing_keys (
              version INT NOT NULL,
              public_key_pem TEXT NOT NULL,
              active_from TIMESTAMP(0) WITH TIME ZONE NOT NULL,
              retired_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
              PRIMARY KEY (version)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE api_tokens');
        $this->addSql('DROP TABLE role_permissions');
        $this->addSql('DROP TABLE roles');
        $this->addSql('DROP TABLE memberships');
        $this->addSql('DROP TABLE companies');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE signing_keys');
    }
}
