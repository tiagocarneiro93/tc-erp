<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Migration;

/**
 * `use`d by migrations that create a company-scoped table, so the RLS
 * policy (technical-scope.md §5.2) is never hand-rolled and never forgotten
 * — a CI schema check separately fails the build if a table with a
 * `company_id` column has no policy.
 *
 * @mixin \Doctrine\Migrations\AbstractMigration
 */
trait CompanyIsolationMigration
{
    protected function enableCompanyIsolation(string $table): void
    {
        $this->addSql(\sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $table));
        $this->addSql(\sprintf('ALTER TABLE %s FORCE ROW LEVEL SECURITY', $table));
        $this->addSql(\sprintf(
            <<<'SQL'
                CREATE POLICY company_isolation ON %s
                  USING      (company_id = current_setting('app.company_id')::uuid)
                  WITH CHECK (company_id = current_setting('app.company_id')::uuid)
                SQL,
            $table,
        ));
    }

    protected function disableCompanyIsolation(string $table): void
    {
        $this->addSql(\sprintf('DROP POLICY company_isolation ON %s', $table));
    }

    /**
     * For insert-only tables (CLAUDE.md — issued fiscal data is immutable):
     * app_owner's `ALTER DEFAULT PRIVILEGES` (docker/postgres/init/02-databases.sql)
     * grants app_runtime UPDATE/DELETE on every new table, so it must be
     * revoked explicitly per table rather than assumed absent.
     */
    protected function makeInsertOnly(string $table): void
    {
        $this->addSql(\sprintf('REVOKE UPDATE, DELETE ON %s FROM app_runtime', $table));
    }
}
