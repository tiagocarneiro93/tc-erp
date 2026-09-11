<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * CI schema check (CLAUDE.md task 0.9, technical-scope.md §5.2): every
 * table with a `company_id` column must have Row-Level Security enabled,
 * forced, and a policy — so forgetting `enableCompanyIsolation()` on a new
 * migration fails `make test`, not code review.
 */
final class CompanyIsolationSchemaTest extends KernelTestCase
{
    /**
     * Global despite the column (technical-scope.md §5.1 lists both as
     * global tables): `memberships` is what determines which companies a
     * user can act as, so it must be readable before any company context
     * exists; `api_tokens` is the same reasoning for token auth — see
     * App\Platform\Domain\ApiToken's docblock.
     */
    private const EXEMPT_TABLES = ['api_tokens', 'memberships'];

    public function testEveryCompanyScopedTableHasRowLevelSecurityEnabledForcedAndPolicied(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        $tables = $connection->fetchFirstColumn(<<<'SQL'
            SELECT DISTINCT table_name
            FROM information_schema.columns
            WHERE table_schema = 'public' AND column_name = 'company_id'
            SQL);

        self::assertNotEmpty($tables, 'Expected at least one company-scoped table (audit_log) to exist.');

        foreach ($tables as $table) {
            self::assertIsString($table);

            if (\in_array($table, self::EXEMPT_TABLES, true)) {
                continue;
            }

            $class = $connection->fetchAssociative(
                'SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE relname = :table',
                ['table' => $table],
            );
            self::assertIsArray($class, \sprintf('Table "%s" not found in pg_class.', $table));
            self::assertTrue((bool) $class['relrowsecurity'], \sprintf('Table "%s" has a company_id column but RLS is not enabled.', $table));
            self::assertTrue((bool) $class['relforcerowsecurity'], \sprintf('Table "%s" has RLS enabled but not forced (FORCE ROW LEVEL SECURITY).', $table));

            $policyCount = $connection->fetchOne('SELECT COUNT(*) FROM pg_policies WHERE tablename = :table', ['table' => $table]);
            self::assertGreaterThan(0, $policyCount, \sprintf('Table "%s" has RLS enabled but no policy.', $table));
        }
    }
}
