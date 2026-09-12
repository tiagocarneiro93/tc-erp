<?php

declare(strict_types=1);

namespace App\Tests\Integration\Migrations;

use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Re-applying Version20260912100000 must not duplicate rows — same
 * pattern as TaxRatesAndExemptionReasonsSeedMigrationTest.
 */
final class ExemptTaxRateSeedMigrationTest extends KernelTestCase
{
    public function testReapplyingTheSeedDoesNotDuplicateRows(): void
    {
        require_once __DIR__.'/../../../migrations/Version20260912100000.php';

        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        $iseRowsBefore = $connection->fetchOne("SELECT COUNT(*) FROM tax_rates WHERE code = 'ISE'");
        self::assertSame(3, $iseRowsBefore);

        $migration = new \DoctrineMigrations\Version20260912100000($connection, new NullLogger());
        $migration->up($connection->createSchemaManager()->introspectSchema());

        foreach ($migration->getSql() as $query) {
            $parameters = [];
            foreach ($query->getParameters() as $name => $value) {
                $parameters[(string) $name] = $value;
            }

            $connection->executeStatement($query->getStatement(), $parameters);
        }

        self::assertSame($iseRowsBefore, $connection->fetchOne("SELECT COUNT(*) FROM tax_rates WHERE code = 'ISE'"));
    }

    public function testEveryRegionHasAZeroPercentExemptRate(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        foreach (['PT', 'PT-AC', 'PT-MA'] as $region) {
            $percentage = $connection->fetchOne(
                'SELECT percentage FROM tax_rates WHERE region = :region AND code = :code',
                ['region' => $region, 'code' => 'ISE'],
            );
            self::assertSame('0.00', $percentage, \sprintf('Region %s should have a 0%% ISE rate.', $region));
        }
    }
}
