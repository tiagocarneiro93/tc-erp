<?php

declare(strict_types=1);

namespace App\Tests\Integration\Migrations;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Version20260920090000 confirmed all nine mainland/Açores/Madeira rates
 * as single eternal values (correcting PT-MA/RED from an unconfirmed 4%
 * candidate to 5%, per the AT portal at the time). Version20260921090000
 * corrected that again: PT-MA/RED's 5% only held until 2024-09-30 —
 * Decreto Legislativo Regional n.º 6/2024/M, de 29 de julho, art. 21.º
 * dropped it to 4% from 2024-10-01 — so the single row became two,
 * date-versioned.
 */
final class RegionalTaxRatesConfirmedMigrationTest extends KernelTestCase
{
    public function testMainlandAndAcoresRatesMatchTheConfirmedValues(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        /** @var list<array{region: string, code: string, percentage: string}> $rows */
        $rows = $connection->fetchAllAssociative(
            "SELECT region, code, percentage FROM tax_rates WHERE region IN ('PT', 'PT-AC') AND code IN ('RED', 'INT', 'NOR') AND valid_from = '2011-01-01'",
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
    }

    public function testMadeiraReducedRateIsDateVersionedAcrossTheOctober2024Change(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        /** @var list<array{percentage: string, valid_from: string, valid_to: ?string}> $rows */
        $rows = $connection->fetchAllAssociative(
            "SELECT percentage, valid_from, valid_to FROM tax_rates WHERE region = 'PT-MA' AND code = 'RED' ORDER BY valid_from",
        );

        self::assertCount(2, $rows, 'PT-MA/RED must be two date-versioned rows, not one eternal value.');

        self::assertSame('5.00', $rows[0]['percentage'], 'Rate before the DLR 6/2024/M change.');
        self::assertStringStartsWith('2024-09-30', (string) $rows[0]['valid_to']);

        self::assertSame('4.00', $rows[1]['percentage'], 'Rate from 1 October 2024 (DLR 6/2024/M art. 21.º).');
        self::assertStringStartsWith('2024-10-01', $rows[1]['valid_from']);
        self::assertNull($rows[1]['valid_to']);
    }

    public function testMadeiraOtherRatesAreUnaffectedByTheReducedRateChange(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        /** @var list<array{code: string, percentage: string}> $rows */
        $rows = $connection->fetchAllAssociative(
            "SELECT code, percentage FROM tax_rates WHERE region = 'PT-MA' AND code IN ('INT', 'NOR') AND valid_from = '2011-01-01'",
        );

        $byCode = [];
        foreach ($rows as $row) {
            $byCode[$row['code']] = $row['percentage'];
        }

        self::assertSame('12.00', $byCode['INT']);
        self::assertSame('22.00', $byCode['NOR']);
    }
}
