<?php

declare(strict_types=1);

namespace App\Tests\Unit\LiveAt;

use App\AtIntegration\Domain\Efatura\EFaturaRequestBuilder;
use App\Tests\LiveAt\Support\RegisteredSeries;
use App\Tests\LiveAt\Support\ScenarioCatalog;
use App\Tests\LiveAt\Support\ScenarioDocumentFactory;
use App\Tests\Support\FatcorewsSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Everything the live suite will send is checked here offline: each shipped
 * scenario becomes a request that validates against AT's own schema, so a bad
 * scenario (or a drift in the builder) is caught before a certificate is needed.
 */
final class ScenarioDocumentFactoryTest extends TestCase
{
    private const NOW = '2026-10-07T10:11:12+00:00';

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function shippedScenarios(): iterable
    {
        foreach (ScenarioCatalog::load(\dirname(__DIR__, 2).'/LiveAt', null) as $scenario) {
            yield $scenario->name => [$scenario->name];
        }
    }

    #[DataProvider('shippedScenarios')]
    public function testEveryShippedScenarioBuildsARequestAtsSchemaAccepts(string $name): void
    {
        $scenario = $this->scenario($name);
        $series = new RegisteredSeries($scenario->documentType, 'LVabc123'.$scenario->documentType, 'ABCD1234');
        $document = (new ScenarioDocumentFactory('508025095'))->build($scenario, $series, ['FT LVabc123FT/1'], new \DateTimeImmutable(self::NOW));

        $request = (new EFaturaRequestBuilder(0))->buildRegister($document);

        self::assertSame([], FatcorewsSchema::validate($request), $name);
    }

    public function testAmountsComeFromThePriceCalculator(): void
    {
        $document = $this->build('FT-basic');

        self::assertSame('100.00', $document->netTotal);
        self::assertSame('23.00', $document->taxTotal);
        self::assertSame('123.00', $document->grossTotal);
    }

    public function testMixedVatGroupsByTaxKeyAndAppliesTheDiscountBeforeVat(): void
    {
        $document = $this->build('FT-mixed-vat');
        $byKey = [];
        foreach ($document->taxSummary as $bucket) {
            $byKey[$bucket->taxCode] = $bucket;
        }

        $keys = array_keys($byKey);
        sort($keys);
        self::assertSame(['INT', 'ISE', 'NOR', 'RED'], $keys);
        // 2 × 49.99 + 4 × 10.00 less 10 % = 99.98 + 36.00 = 135.98 taxed at 23 %
        self::assertSame('135.98', $byKey['NOR']->taxableBase);
        self::assertSame('31.28', $byKey['NOR']->taxAmount);
        self::assertSame('0.00', $byKey['ISE']->taxAmount);
        self::assertContains('M07', array_map(static fn ($line) => $line->exemptionReasonCode, $document->lines));
    }

    public function testNumbersFollowTheSeriesAndTheAtcudUsesItsValidationCode(): void
    {
        $series = new RegisteredSeries('FT', 'LVabc123FT', 'ABCD1234');
        $factory = new ScenarioDocumentFactory('508025095');
        $scenario = $this->scenario('FT-basic');

        $first = $factory->build($scenario, $series, [], new \DateTimeImmutable(self::NOW));
        $second = $factory->build($scenario, $series, [], new \DateTimeImmutable(self::NOW));

        self::assertSame('FT LVabc123FT/1', $first->documentNo);
        self::assertSame('ABCD1234-1', $first->atcud);
        self::assertSame('FT LVabc123FT/2', $second->documentNo);
        self::assertSame('508025095', $first->issuerNif);
        self::assertSame('SalesInvoices', $first->saftSection);
        self::assertSame('WorkingDocuments', $this->build('OR-basic')->saftSection);
    }

    public function testACreditNoteCarriesTheReferencesItWasGiven(): void
    {
        $document = $this->build('NC-basic', ['FT LVabc123FT/1']);

        self::assertSame(['FT LVabc123FT/1'], $document->referencedDocumentNos);
    }

    public function testTheWrongTotalsScenarioIsInconsistentOnPurpose(): void
    {
        $document = $this->build('FT-wrong-totals');

        self::assertSame('100.00', $document->netTotal);
        self::assertSame('124.00', $document->grossTotal, 'Gross is net + tax + 1.');
    }

    public function testACancelledDocumentIsRegisteredWithStatusA(): void
    {
        self::assertSame('A', $this->build('FT-cancelled')->status);
    }

    public function testTheContinentalTaxTableIsTheOnlyOneSupported(): void
    {
        $scenario = ScenarioCatalog::build(['x' => ['document_type' => 'FT', 'lines' => [['tax_region' => 'PT-MA']]]], [], null)[0];

        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('PT-MA');

        (new ScenarioDocumentFactory('508025095'))->build($scenario, new RegisteredSeries('FT', 'X', 'ABCD1234'), [], new \DateTimeImmutable(self::NOW));
    }

    private function scenario(string $name): \App\Tests\LiveAt\Support\DocumentScenario
    {
        foreach (ScenarioCatalog::load(\dirname(__DIR__, 2).'/LiveAt', null) as $scenario) {
            if ($scenario->name === $name) {
                return $scenario;
            }
        }

        self::fail('No shipped scenario '.$name);
    }

    /**
     * @param list<string> $references
     */
    private function build(string $name, array $references = []): \App\Shared\Domain\AtIntegration\AtCommunicableDocument
    {
        $scenario = $this->scenario($name);

        return (new ScenarioDocumentFactory('508025095'))->build($scenario, new RegisteredSeries($scenario->documentType, 'LVabc123'.$scenario->documentType, 'ABCD1234'), $references, new \DateTimeImmutable(self::NOW));
    }
}
