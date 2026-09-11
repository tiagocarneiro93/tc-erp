<?php

declare(strict_types=1);

namespace App\Tests\Integration\Migrations;

use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * docs/plans/phase-1.md task 1.1 acceptance: re-applying the seed migration
 * must not duplicate rows. The migration itself already ran once as part
 * of `make migrate` for this test database; this re-applies its queued SQL
 * directly and checks the row counts are unchanged.
 *
 * config/packages/doctrine_migrations.yaml deliberately keeps
 * `DoctrineMigrations\*` out of Composer's autoloader, so the class is
 * `require`d directly rather than imported.
 */
final class CountriesAndUnitsSeedMigrationTest extends KernelTestCase
{
    public function testReapplyingTheSeedDoesNotDuplicateRows(): void
    {
        require_once __DIR__.'/../../../migrations/Version20260911205600.php';

        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        $countriesBefore = $connection->fetchOne('SELECT COUNT(*) FROM countries');
        $unitsBefore = $connection->fetchOne('SELECT COUNT(*) FROM units');
        self::assertGreaterThan(0, $countriesBefore, 'Expected the countries table to already be seeded.');
        self::assertGreaterThan(0, $unitsBefore, 'Expected the units table to already be seeded.');

        $migration = new \DoctrineMigrations\Version20260911205600($connection, new NullLogger());
        $migration->up($connection->createSchemaManager()->introspectSchema());

        foreach ($migration->getSql() as $query) {
            $parameters = [];
            foreach ($query->getParameters() as $name => $value) {
                $parameters[(string) $name] = $value;
            }

            $connection->executeStatement($query->getStatement(), $parameters);
        }

        self::assertSame($countriesBefore, $connection->fetchOne('SELECT COUNT(*) FROM countries'));
        self::assertSame($unitsBefore, $connection->fetchOne('SELECT COUNT(*) FROM units'));
    }
}
