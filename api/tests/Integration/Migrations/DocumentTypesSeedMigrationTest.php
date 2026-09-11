<?php

declare(strict_types=1);

namespace App\Tests\Integration\Migrations;

use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * docs/plans/phase-1.md task 1.3 acceptance: exactly the twelve v1 types,
 * with flags matching technical-scope.md §6.6; re-applying must not
 * duplicate rows. See CountriesAndUnitsSeedMigrationTest's docblock for
 * why the migration class is `require`d directly.
 */
final class DocumentTypesSeedMigrationTest extends KernelTestCase
{
    public function testSeedsExactlyTwelveTypesWithTheExpectedFlags(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        $rows = $connection->fetchAllAssociativeIndexed('SELECT code, saft_section, signed, stock_effect, account_effect, requires_at_prior_communication FROM document_types');
        self::assertCount(12, $rows);

        $expectedCodes = ['FT', 'FS', 'FR', 'NC', 'ND', 'RG', 'GT', 'GR', 'GD', 'OR', 'PF', 'NE'];
        $actualCodes = array_map('strval', array_keys($rows));
        sort($expectedCodes);
        sort($actualCodes);
        self::assertSame($expectedCodes, $actualCodes, 'mismatched or missing code');

        // signed = false for RG only (technical-scope.md §6.6 comment).
        foreach ($rows as $code => $row) {
            self::assertSame('RG' === $code, !(bool) $row['signed'], \sprintf('%s signed flag', $code));
        }

        self::assertSame('MovementOfGoods', $rows['GT']['saft_section']);
        self::assertTrue((bool) $rows['GT']['requires_at_prior_communication']);
        self::assertTrue((bool) $rows['GR']['requires_at_prior_communication']);
        self::assertTrue((bool) $rows['GD']['requires_at_prior_communication']);
        self::assertFalse((bool) $rows['FT']['requires_at_prior_communication']);
    }

    public function testReapplyingTheSeedDoesNotDuplicateRows(): void
    {
        require_once __DIR__.'/../../../migrations/Version20260911212200.php';

        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        $before = $connection->fetchOne('SELECT COUNT(*) FROM document_types');
        self::assertSame(12, $before);

        $migration = new \DoctrineMigrations\Version20260911212200($connection, new NullLogger());
        $migration->up($connection->createSchemaManager()->introspectSchema());

        foreach ($migration->getSql() as $query) {
            $parameters = [];
            foreach ($query->getParameters() as $name => $value) {
                $parameters[(string) $name] = $value;
            }

            // The migration only ever binds 'boolean' types explicitly
            // (see Version20260911212200::up()); Query::getTypes() carries
            // a weak `mixed[]` docblock, so re-declare them here instead of
            // relaying its return value untyped.
            $types = [
                'signed' => 'boolean',
                'requires_at_prior_communication' => 'boolean',
            ];

            $connection->executeStatement($query->getStatement(), $parameters, $types);
        }

        self::assertSame($before, $connection->fetchOne('SELECT COUNT(*) FROM document_types'));
    }
}
