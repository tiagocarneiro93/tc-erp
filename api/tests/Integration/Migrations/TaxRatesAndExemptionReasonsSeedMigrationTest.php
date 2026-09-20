<?php

declare(strict_types=1);

namespace App\Tests\Integration\Migrations;

use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * docs/plans/phase-1.md task 1.2 acceptance: re-applying the seed migration
 * must not duplicate rows. Mirrors
 * CountriesAndUnitsSeedMigrationTest -- see its docblock for why the
 * migration class is `require`d directly.
 */
final class TaxRatesAndExemptionReasonsSeedMigrationTest extends KernelTestCase
{
    public function testReapplyingTheSeedDoesNotDuplicateRows(): void
    {
        require_once __DIR__.'/../../../migrations/Version20260911211300.php';

        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        $exemptionReasonsBefore = $connection->fetchOne('SELECT COUNT(*) FROM exemption_reasons');
        $taxRatesBefore = $connection->fetchOne('SELECT COUNT(*) FROM tax_rates');
        self::assertSame(33, $exemptionReasonsBefore);
        // 9 RED/INT/NOR rows from this migration + 3 ISE rows seeded by
        // Version20260912100000 + 1 extra PT-MA/RED row from
        // Version20260921090000 splitting it into two date-versioned rows.
        self::assertSame(13, $taxRatesBefore);

        $migration = new \DoctrineMigrations\Version20260911211300($connection, new NullLogger());
        $migration->up($connection->createSchemaManager()->introspectSchema());

        foreach ($migration->getSql() as $query) {
            $parameters = [];
            foreach ($query->getParameters() as $name => $value) {
                $parameters[(string) $name] = $value;
            }

            $connection->executeStatement($query->getStatement(), $parameters);
        }

        self::assertSame($exemptionReasonsBefore, $connection->fetchOne('SELECT COUNT(*) FROM exemption_reasons'));
        self::assertSame($taxRatesBefore, $connection->fetchOne('SELECT COUNT(*) FROM tax_rates'));
    }
}
