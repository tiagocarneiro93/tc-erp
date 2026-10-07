<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtIntegration\Domain\Efatura;

use App\AtIntegration\Domain\Efatura\EFaturaRequestBuilder;
use App\Shared\Domain\AtIntegration\AtCommunicableDocument;
use App\Shared\Domain\AtIntegration\AtCommunicableLine;
use App\Shared\Domain\AtIntegration\AtCommunicableTaxBucket;
use App\Tests\Support\FatcorewsSchema;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Every body is validated against the XSD embedded in AT's own
 * `Fatcorews.wsdl` ({@see FatcorewsSchema}), then spot-checked for the
 * semantic choices the schema cannot express (D/C indicator, amount
 * reconciliation, the "0" hash characters while uncertified).
 */
final class EFaturaRequestBuilderTest extends TestCase
{
    public function testAnInvoiceBodyIsValidAgainstAtsOwnSchema(): void
    {
        $xml = (new EFaturaRequestBuilder())->buildRegister($this->invoice());

        self::assertSame([], FatcorewsSchema::validate($xml));
        self::assertStringStartsWith('<doc:RegisterInvoiceRequest xmlns:doc="http://factemi.at.min_financas.pt/documents">', $xml);
    }

    public function testAnInvoiceCarriesTheStoredValuesVerbatim(): void
    {
        $xml = $this->parse((new EFaturaRequestBuilder())->buildRegister($this->invoice()));

        self::assertSame('FT 2026A/1', $this->text($xml, '//d:InvoiceData/d:InvoiceNo'));
        self::assertSame('CSDF7T5H-1', $this->text($xml, '//d:InvoiceData/d:ATCUD'));
        self::assertSame('2026-03-05', $this->text($xml, '//d:InvoiceDate'));
        self::assertSame('FT', $this->text($xml, '//d:InvoiceType'));
        self::assertSame('N', $this->text($xml, '//d:InvoiceStatus'));
        self::assertSame('2026-03-05T10:11:12', $this->text($xml, '//d:SystemEntryDate'));
        self::assertSame('100.00', $this->text($xml, '//d:LineSummary/d:Amount'));
        self::assertSame('23.00', $this->text($xml, '//d:LineSummary/d:Tax/d:TaxPercentage'));
        self::assertSame('23.00', $this->text($xml, '//d:TaxPayable'));
        self::assertSame('100.00', $this->text($xml, '//d:NetTotal'));
        self::assertSame('123.00', $this->text($xml, '//d:GrossTotal'));
        self::assertSame('508025090', $this->text($xml, '//d:TaxRegistrationNumber'));
        self::assertSame('Global', $this->text($xml, '//d:TaxEntity'));
        self::assertSame('0', $this->text($xml, '//d:SoftwareCertificateNumber'));
    }

    public function testHashCharactersAreZeroWhileTheSoftwareIsUncertifiedAndTheRealOnesOnceItIs(): void
    {
        $uncertified = $this->parse((new EFaturaRequestBuilder(0))->buildRegister($this->invoice()));
        $certified = $this->parse((new EFaturaRequestBuilder(1234))->buildRegister($this->invoice()));

        self::assertSame('0', $this->text($uncertified, '//d:HashCharacters'));
        self::assertSame('AbCd', $this->text($certified, '//d:HashCharacters'));
        self::assertSame('1234', $this->text($certified, '//d:SoftwareCertificateNumber'));
    }

    public function testACreditNoteIsADebitLineAndReferencesTheRectifiedInvoice(): void
    {
        $document = $this->invoice(type: 'NC', referenced: ['FT 2026A/1']);
        $body = (new EFaturaRequestBuilder())->buildRegister($document);

        self::assertSame([], FatcorewsSchema::validate($body));
        $xml = $this->parse($body);
        self::assertSame('D', $this->text($xml, '//d:LineSummary/d:DebitCreditIndicator'));
        self::assertSame('FT 2026A/1', $this->text($xml, '//d:LineSummary/d:Reference'));
    }

    public function testAnExemptLineCarriesItsExemptionCode(): void
    {
        $document = $this->document(
            lines: [new AtCommunicableLine('PT', 'ISE', '0.00', 'M07', '50.00', new \DateTimeImmutable('2026-03-05'), [])],
            taxSummary: [new AtCommunicableTaxBucket('PT', 'ISE', '0.00', '50.00', '0.00')],
            net: '50.00',
            tax: '0.00',
            gross: '50.00',
        );
        $body = (new EFaturaRequestBuilder())->buildRegister($document);

        self::assertSame([], FatcorewsSchema::validate($body));
        self::assertSame('M07', $this->text($this->parse($body), '//d:TaxExemptionCode'));
    }

    public function testGroupAmountsAlwaysAddUpToTheAuthoritativeTaxBucket(): void
    {
        // Three 0.10 lines under per_group rounding: line nets are informational, the bucket is 0.30.
        // Split across two tax point dates, with a fractional line net that would round to 0.13.
        $document = $this->document(
            lines: [
                new AtCommunicableLine('PT', 'NOR', '23.00', null, '0.100000', new \DateTimeImmutable('2026-03-01'), []),
                new AtCommunicableLine('PT', 'NOR', '23.00', null, '0.125000', new \DateTimeImmutable('2026-03-02'), []),
                new AtCommunicableLine('PT', 'NOR', '23.00', null, '0.075000', new \DateTimeImmutable('2026-03-02'), []),
            ],
            taxSummary: [new AtCommunicableTaxBucket('PT', 'NOR', '23.00', '0.30', '0.07')],
            net: '0.30',
            tax: '0.07',
            gross: '0.37',
        );
        $body = (new EFaturaRequestBuilder())->buildRegister($document);

        self::assertSame([], FatcorewsSchema::validate($body));
        $amounts = $this->texts($this->parse($body), '//d:LineSummary/d:Amount');
        self::assertCount(2, $amounts);
        self::assertSame('0.30', array_reduce($amounts, static fn (BigDecimal $sum, string $amount): BigDecimal => $sum->plus($amount), BigDecimal::zero())->toScale(2)->toString());
    }

    public function testConvertedDocumentsReferenceTheirOrigin(): void
    {
        $document = $this->document(
            lines: [new AtCommunicableLine('PT', 'NOR', '23.00', null, '100.00', new \DateTimeImmutable('2026-03-05'), ['OR 2026A/7'])],
            taxSummary: [new AtCommunicableTaxBucket('PT', 'NOR', '23.00', '100.00', '23.00')],
        );
        $body = (new EFaturaRequestBuilder())->buildRegister($document);

        self::assertSame([], FatcorewsSchema::validate($body));
        self::assertSame('OR 2026A/7', $this->text($this->parse($body), '//d:OrderReferences/d:OriginatingON'));
    }

    public function testAWorkingDocumentIsRegisteredAsWork(): void
    {
        $builder = new EFaturaRequestBuilder();
        $document = $this->invoice(type: 'OR', section: 'WorkingDocuments');

        self::assertSame('RegisterWork', $builder->operationFor($document));
        $body = $builder->buildRegister($document);

        self::assertSame([], FatcorewsSchema::validate($body));
        $xml = $this->parse($body);
        self::assertSame('OR', $this->text($xml, '//d:WorkData/d:WorkType'));
        self::assertSame('FT 2026A/1', $this->text($xml, '//d:WorkData/d:DocumentNumber'));
        self::assertSame('N', $this->text($xml, '//d:WorkStatus'));
    }

    public function testChangingAWorkStatusIsValidAgainstAtsOwnSchema(): void
    {
        $document = $this->invoice(type: 'OR', section: 'WorkingDocuments', status: 'F');
        $body = (new EFaturaRequestBuilder())->buildChangeWorkStatus($document);

        self::assertSame([], FatcorewsSchema::validate($body));
        $xml = $this->parse($body);
        self::assertSame('F', $this->text($xml, '//d:WorkStatus/d:WorkStatus'));
        self::assertSame('2026-03-06T08:00:00', $this->text($xml, '//d:WorkStatus/d:WorkStatusDate'));
    }

    public function testACancelledDocumentIsRegisteredWithStatusA(): void
    {
        $body = (new EFaturaRequestBuilder())->buildRegister($this->invoice(status: 'A'));

        self::assertSame([], FatcorewsSchema::validate($body));
        self::assertSame('A', $this->text($this->parse($body), '//d:InvoiceStatus'));
    }

    public function testSpecialCharactersAreEscaped(): void
    {
        $document = $this->invoice(documentNo: 'FT 2026A/1', customerTaxId: 'A&B<1>');
        $body = (new EFaturaRequestBuilder())->buildRegister($document);

        self::assertStringContainsString('A&amp;B&lt;1&gt;', $body);
        self::assertSame('A&B<1>', $this->text($this->parse($body), '//d:CustomerTaxID'));
    }

    public function testAnInvalidIssuerNifIsRefused(): void
    {
        $this->expectException(\DomainException::class);

        (new EFaturaRequestBuilder())->buildRegister($this->invoice(issuerNif: ''));
    }

    public function testADocumentOutsideTheEFaturaSectionsIsRefused(): void
    {
        $this->expectException(\DomainException::class);

        (new EFaturaRequestBuilder())->operationFor($this->invoice(type: 'RG', section: 'Payments'));
    }

    /**
     * @param list<string> $referenced
     */
    private function invoice(string $type = 'FT', string $section = 'SalesInvoices', string $status = 'N', array $referenced = [], string $documentNo = 'FT 2026A/1', string $customerTaxId = '123456789', string $issuerNif = '508025090'): AtCommunicableDocument
    {
        return $this->document(
            type: $type,
            section: $section,
            status: $status,
            referenced: $referenced,
            documentNo: $documentNo,
            customerTaxId: $customerTaxId,
            issuerNif: $issuerNif,
        );
    }

    /**
     * @param list<string>                       $referenced
     * @param list<AtCommunicableLine>|null      $lines
     * @param list<AtCommunicableTaxBucket>|null $taxSummary
     */
    private function document(
        string $type = 'FT',
        string $section = 'SalesInvoices',
        string $status = 'N',
        array $referenced = [],
        string $documentNo = 'FT 2026A/1',
        string $customerTaxId = '123456789',
        string $issuerNif = '508025090',
        ?array $lines = null,
        ?array $taxSummary = null,
        string $net = '100.00',
        string $tax = '23.00',
        string $gross = '123.00',
    ): AtCommunicableDocument {
        return new AtCommunicableDocument(
            id: '0192e0f0-0000-7000-8000-000000000001',
            documentType: $type,
            saftSection: $section,
            documentNo: $documentNo,
            atcud: 'CSDF7T5H-1',
            issuerNif: $issuerNif,
            customerTaxId: $customerTaxId,
            customerCountry: 'PT',
            issueDate: new \DateTimeImmutable('2026-03-05T10:11:12+00:00'),
            systemEntryAt: new \DateTimeImmutable('2026-03-05T10:11:12+00:00'),
            status: $status,
            statusAt: 'F' === $status ? new \DateTimeImmutable('2026-03-06T08:00:00+00:00') : new \DateTimeImmutable('2026-03-05T10:11:12+00:00'),
            hashCharacters: 'AbCd',
            cashVatScheme: false,
            netTotal: $net,
            taxTotal: $tax,
            grossTotal: $gross,
            referencedDocumentNos: $referenced,
            lines: $lines ?? [new AtCommunicableLine('PT', 'NOR', '23.00', null, '100.000000', new \DateTimeImmutable('2026-03-05'), [])],
            taxSummary: $taxSummary ?? [new AtCommunicableTaxBucket('PT', 'NOR', '23.00', '100.00', '23.00')],
        );
    }

    private function parse(string $body): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadXML($body);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('d', EFaturaRequestBuilder::NAMESPACE);

        return $xpath;
    }

    private function text(\DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        self::assertNotFalse($nodes);
        self::assertGreaterThan(0, $nodes->length, \sprintf('No node for %s', $query));

        $node = $nodes->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);

        return $node->textContent;
    }

    /**
     * @return list<string>
     */
    private function texts(\DOMXPath $xpath, string $query): array
    {
        $nodes = $xpath->query($query);
        self::assertNotFalse($nodes);
        $values = [];

        foreach ($nodes as $node) {
            self::assertInstanceOf(\DOMElement::class, $node);
            $values[] = $node->textContent;
        }

        return $values;
    }
}
