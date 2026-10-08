<?php

declare(strict_types=1);

namespace App\Tests\Unit\LiveAt;

use App\Tests\LiveAt\Support\ScenarioCatalog;
use PHPUnit\Framework\TestCase;

final class ScenarioCatalogTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private const DEFAULTS = [
        'FT-a' => ['document_type' => 'FT', 'lines' => [['unit_price' => '10.00']]],
        'NC-a' => ['document_type' => 'NC', 'references' => ['FT-a'], 'lines' => [['unit_price' => '10.00']]],
        'OR-a' => ['document_type' => 'OR', 'lines' => [['quantity' => '2']]],
    ];

    public function testTheShippedDefaultsCoverEveryDocumentTypeTheSuiteSends(): void
    {
        $scenarios = ScenarioCatalog::load(\dirname(__DIR__, 2).'/LiveAt', null);
        $types = array_values(array_unique(array_map(static fn ($s) => $s->documentType, $scenarios)));
        sort($types);

        self::assertSame(['FR', 'FS', 'FT', 'NC', 'ND', 'NE', 'OR', 'PF'], $types);
        self::assertContains('rejected', array_map(static fn ($s) => $s->expect, $scenarios), 'There is a deliberately wrong document.');
    }

    public function testKeysAreFilledWithDefaults(): void
    {
        $ft = ScenarioCatalog::build(self::DEFAULTS, [], null)[0];

        self::assertSame('999999990', $ft->customerTaxId);
        self::assertSame('PT', $ft->customerCountry);
        self::assertSame('N', $ft->status);
        self::assertSame('accepted', $ft->expect);
        self::assertSame('1', $ft->lines[0]['quantity']);
        self::assertSame('NOR', $ft->lines[0]['tax_code']);
        self::assertSame('10.00', $ft->lines[0]['unit_price']);
    }

    public function testALocalOverrideReplacesOnlyTheKeysItNames(): void
    {
        $scenarios = ScenarioCatalog::build(self::DEFAULTS, ['FT-a' => ['customer' => ['tax_id' => '508025090'], 'lines' => [['unit_price' => '99.00', 'tax_code' => 'RED']]]], null);

        self::assertSame('508025090', $scenarios[0]->customerTaxId);
        self::assertSame('PT', $scenarios[0]->customerCountry, 'The rest of the customer keeps its default.');
        self::assertSame('99.00', $scenarios[0]->lines[0]['unit_price']);
        self::assertSame('RED', $scenarios[0]->lines[0]['tax_code']);
        self::assertSame('FT', $scenarios[0]->documentType);
    }

    public function testALocalFileCanAddAndDisableScenarios(): void
    {
        $scenarios = ScenarioCatalog::build(self::DEFAULTS, ['OR-a' => ['enabled' => false], 'FT-big' => ['document_type' => 'FT', 'lines' => [['quantity' => '1000']]]], null);

        self::assertSame(['FT-a', 'NC-a', 'FT-big'], array_map(static fn ($s) => $s->name, $scenarios));
    }

    public function testAScenarioWithoutLinesOrTypeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scenario "broken" needs a document_type and at least one line');

        ScenarioCatalog::build(self::DEFAULTS, ['broken' => ['document_type' => 'FT']], null);
    }

    public function testOnlyNarrowsByNameOrByDocumentType(): void
    {
        self::assertSame(['NC-a'], array_map(static fn ($s) => $s->name, ScenarioCatalog::build(self::DEFAULTS, [], 'NC-a')));
        self::assertSame(['FT-a', 'OR-a'], array_map(static fn ($s) => $s->name, ScenarioCatalog::build(self::DEFAULTS, [], ' FT , OR ')));
    }
}
