<?php

declare(strict_types=1);

namespace App\Tests\Integration\Migrations;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Version20260920090000 corrects PT-MA's reduced VAT rate (seeded as an
 * unconfirmed 4% candidate by task 1.2, now confirmed at 5% against the
 * official AT portal) and drops the "unconfirmed" wording from every
 * PT-AC/PT-MA row's description.
 */
final class RegionalTaxRatesConfirmedMigrationTest extends KernelTestCase
{
    public function testAllNineMainlandAndRegionalRatesMatchTheConfirmedValues(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        /** @var list<array{region: string, code: string, percentage: string}> $rows */
        $rows = $connection->fetchAllAssociative(
            "SELECT region, code, percentage FROM tax_rates WHERE code IN ('RED', 'INT', 'NOR') AND valid_from = '2011-01-01'",
        );

        /** @var array<string, array<string, string>> $byRegionAndCode */
        $byRegionAndCode = [];
        foreach ($rows as $row) {
            $byRegionAndCode[$row['region']][$row['code']] = $row['percentage'];
        }

        self::assertSame('6.00', $byRegionAndCode['PT']['RED']);
        self::assertSame('13.00', $byRegionAndCode['PT']['INT']);
        self::assertSame('23.00', $byRegionAndCode['PT']['NOR']);

        self::assertSame('4.00', $byRegionAndCode['PT-AC']['RED']);
        self::assertSame('9.00', $byRegionAndCode['PT-AC']['INT']);
        self::assertSame('16.00', $byRegionAndCode['PT-AC']['NOR']);

        self::assertSame('5.00', $byRegionAndCode['PT-MA']['RED'], 'Madeira reduced rate was corrected from an unconfirmed 4% to the confirmed 5%.');
        self::assertSame('12.00', $byRegionAndCode['PT-MA']['INT']);
        self::assertSame('22.00', $byRegionAndCode['PT-MA']['NOR']);
    }

    public function testNoRegionalRateDescriptionIsFlaggedAsUnconfirmedAnymore(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        /** @var list<string> $descriptions */
        $descriptions = $connection->fetchFirstColumn(
            "SELECT description FROM tax_rates WHERE region IN ('PT-AC', 'PT-MA') AND code IN ('RED', 'INT', 'NOR')",
        );

        self::assertCount(6, $descriptions);
        foreach ($descriptions as $description) {
            self::assertStringNotContainsString('não confirmado', $description);
        }
    }
}
