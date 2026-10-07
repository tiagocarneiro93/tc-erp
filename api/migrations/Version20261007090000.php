<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * docs/plans/phase-3.md task 3.2 / technical-scope.md §5.4: "Platform-wide
 * jobs (e.g. the AT outbox sweeper) first list pending work through a
 * narrow `SECURITY DEFINER` function that returns only `(company_id,
 * item_id)` pairs, then process each item inside that company's context.
 * No runtime role ever has `BYPASSRLS`."
 *
 * `at_communications` has `FORCE ROW LEVEL SECURITY` (every company-scoped
 * table does), which applies to the table owner too — so a function owned
 * by `app_owner` would still hit the `company_isolation` policy and fail on
 * the unset `app.company_id`. The `sweeper_scan` policy below is the
 * smallest thing that makes the function work: `SELECT` only, and `TO
 * app_owner` only. A `SECURITY DEFINER` function runs as its owner, so
 * inside it `current_user` is `app_owner` and this policy applies; a normal
 * runtime session (`app_runtime`) is never covered by it, so it still sees
 * exactly one company's rows. `app_runtime` can reach other companies' rows
 * only through the function, and the function returns identifiers only.
 */
final class Version20261007090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'at_communications sweeper: SECURITY DEFINER due-list function';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE POLICY sweeper_scan ON at_communications FOR SELECT TO app_owner USING (true)');

        $this->addSql(<<<'SQL'
            CREATE FUNCTION at_communications_due(p_now TIMESTAMPTZ, p_limit INT, p_max_attempts INT)
            RETURNS TABLE (company_id UUID, id UUID)
            LANGUAGE sql
            STABLE
            SECURITY DEFINER
            SET search_path = public, pg_temp
            AS $$
              SELECT c.company_id, c.id
              FROM at_communications c
              WHERE c.status IN ('pending', 'failed', 'sending')
                AND c.next_attempt_at <= p_now
                AND c.attempts < p_max_attempts
              ORDER BY c.next_attempt_at, c.id
              LIMIT p_limit
            $$
            SQL);
        $this->addSql('REVOKE ALL ON FUNCTION at_communications_due(TIMESTAMPTZ, INT, INT) FROM PUBLIC');
        $this->addSql('GRANT EXECUTE ON FUNCTION at_communications_due(TIMESTAMPTZ, INT, INT) TO app_runtime');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP FUNCTION at_communications_due(TIMESTAMPTZ, INT, INT)');
        $this->addSql('DROP POLICY sweeper_scan ON at_communications');
    }
}
