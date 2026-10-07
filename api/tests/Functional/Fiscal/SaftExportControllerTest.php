<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fiscal;

use App\Fiscal\Infrastructure\Saft\Xsd10Projection;
use App\Tests\Support\Dom;
use App\Tests\Support\FiscalFlowHelpers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-3.md task 3.3 against real issued documents (never
 * hand-written XML): everything goes through the issuance use case, then
 * `GET /saft` — which itself validates against the official XSD before
 * answering — and this test validates the downloaded bytes again,
 * independently, with `DOMDocument`.
 */
final class SaftExportControllerTest extends WebTestCase
{
    use FiscalFlowHelpers;

    private const NS = 'urn:OECD:StandardAuditFile-Tax:PT_1.04_01';

    public function testARepresentativeMixOfDocumentsExportsAsAValidSaftFile(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client, 'Empresa Exportadora Lda');
        $customerNif = $this->uniqueNif();
        $customerId = $this->createCustomer($client, $companyId, 'Cliente Um', $customerNif);

        $ftSeries = $this->createActiveSeries($client, $companyId, 'FT', '2026A');
        $ncSeries = $this->createActiveSeries($client, $companyId, 'NC', '2026A');
        $orSeries = $this->createActiveSeries($client, $companyId, 'OR', '2026A');
        $rgSeries = $this->createActiveSeries($client, $companyId, 'RG', '2026A');

        // FT 2026A/1: a named customer, a 23% line and an exempt line.
        $ftId = $this->issueDocument($client, $companyId, $ftSeries, 'FT', [
            $this->widgetLine('2', '10.00'),
            ['product_code' => 'SKU-2', 'description' => 'Livro', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => '50.00', 'tax_region' => 'PT', 'tax_code' => 'ISE', 'exemption_reason_code' => 'M07'],
        ], extraPayload: ['customer_id' => $customerId]);

        // FT 2026A/2: a consumer sale, then cancelled.
        $cancelledId = $this->issueDocument($client, $companyId, $ftSeries, 'FT');
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$cancelledId}/cancel", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // NC 2026A/1 rectifying FT 2026A/1.
        $this->issueDocument($client, $companyId, $ncSeries, 'NC', [$this->widgetLine('1', '10.00')], extraPayload: ['customer_id' => $customerId, 'references' => [['referenced_document_no' => 'FT 2026A/1', 'reason' => 'Devolução']]]);

        // OR 2026A/1 and a receipt settling part of FT 2026A/1.
        $this->issueDocument($client, $companyId, $orSeries, 'OR');
        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'saft-receipt'], content: json_encode([
            'series_id' => $rgSeries,
            'customer_id' => $customerId,
            'payment_method' => 'transfer',
            'allocations' => [['document_id' => $ftId, 'amount' => '20.00']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $year = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');
        $client->catchExceptions(false);
        $client->request('GET', "/api/v1/companies/{$companyId}/saft?from={$year}-01-01&to={$year}-12-31", server: self::HEADERS);
        self::assertResponseIsSuccessful();

        $response = $client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        self::assertMatchesRegularExpression('/^attachment; filename=SAFT_\d{9}_'.$year.'0101_'.$year.'1231\.xml$/', (string) $response->headers->get('Content-Disposition'));
        // BinaryFileResponse deletes its file once sent; the browser has captured the body by then.
        $xml = $client->getInternalResponse()->getContent();
        self::assertSame(hash('sha256', $xml), $response->headers->get('X-Content-SHA256'));

        $this->assertValidAgainstOfficialSchema($xml);

        $document = new \DOMDocument();
        $document->loadXML($xml);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('s', self::NS);

        // Header
        self::assertSame('1.04_01', $this->text($xpath, '/s:AuditFile/s:Header/s:AuditFileVersion'));
        self::assertSame('F', $this->text($xpath, '//s:Header/s:TaxAccountingBasis'));
        self::assertSame('Empresa Exportadora Lda', $this->text($xpath, '//s:Header/s:CompanyName'));
        self::assertSame($year, $this->text($xpath, '//s:Header/s:FiscalYear'));
        self::assertSame($year.'-01-01', $this->text($xpath, '//s:Header/s:StartDate'));
        self::assertSame('0', $this->text($xpath, '//s:Header/s:SoftwareCertificateNumber'));
        self::assertSame('tc-erp/TCWeb', $this->text($xpath, '//s:Header/s:ProductID'));

        // Master files: the named customer and the anonymous consumer, once each.
        self::assertSame(['Cliente Um', 'Consumidor final'], $this->texts($xpath, '//s:MasterFiles/s:Customer/s:CompanyName', sorted: true));
        self::assertCount(2, Dom::elements($xpath, '//s:MasterFiles/s:Product'));
        self::assertSame(['ISE', 'NOR'], $this->texts($xpath, '//s:TaxTable/s:TaxTableEntry/s:TaxCode', sorted: true));

        // SalesInvoices: 3 entries (FT, cancelled FT, NC); the cancelled one is counted but not summed.
        self::assertSame('3', $this->text($xpath, '//s:SalesInvoices/s:NumberOfEntries'));
        self::assertSame('70.00', $this->text($xpath, '//s:SalesInvoices/s:TotalCredit'), 'FT 1 only: 2×10.00 + 50.00; the cancelled FT 2 is left out.');
        self::assertSame('10.00', $this->text($xpath, '//s:SalesInvoices/s:TotalDebit'), 'The credit note\'s line.');

        $cancelled = Dom::first($xpath, "//s:Invoice[s:InvoiceNo='FT 2026A/2']");
        self::assertNotNull($cancelled);
        self::assertSame('A', $this->text($xpath, 's:DocumentStatus/s:InvoiceStatus', $cancelled));

        $first = Dom::first($xpath, "//s:Invoice[s:InvoiceNo='FT 2026A/1']");
        self::assertNotNull($first);
        self::assertSame('1', $this->text($xpath, 's:HashControl', $first));
        self::assertSame(172, \strlen($this->text($xpath, 's:Hash', $first)), 'The full RSA signature, base64.');
        self::assertSame('70.00', $this->text($xpath, 's:DocumentTotals/s:NetTotal', $first));
        self::assertSame('74.60', $this->text($xpath, 's:DocumentTotals/s:GrossTotal', $first));
        $exempt = Dom::first($xpath, "s:Line[s:ProductCode='SKU-2']", $first);
        self::assertNotNull($exempt);
        self::assertSame('M07', $this->text($xpath, 's:TaxExemptionCode', $exempt));
        self::assertSame('50.00', $this->text($xpath, 's:CreditAmount', $exempt));

        $creditNote = Dom::first($xpath, "//s:Invoice[s:InvoiceType='NC']");
        self::assertNotNull($creditNote);
        self::assertSame('10.00', $this->text($xpath, 's:Line/s:DebitAmount', $creditNote));
        self::assertSame('FT 2026A/1', $this->text($xpath, 's:Line/s:References/s:Reference', $creditNote));

        // WorkingDocuments and Payments
        self::assertSame('1', $this->text($xpath, '//s:WorkingDocuments/s:NumberOfEntries'));
        self::assertSame('1', $this->text($xpath, '//s:Payments/s:NumberOfEntries'));
        self::assertSame('20.00', $this->text($xpath, '//s:Payments/s:TotalCredit'));
        self::assertSame('RG', $this->text($xpath, '//s:Payment/s:PaymentType'));
        self::assertSame('TB', $this->text($xpath, '//s:Payment/s:PaymentMethod/s:PaymentMechanism'));
        self::assertSame('FT 2026A/1', $this->text($xpath, '//s:Payment/s:Line/s:SourceDocumentID/s:OriginatingON'));
    }

    public function testTheCashVatIndicatorFollowsTheCompanysCurrentRegime(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $client->request('GET', "/api/v1/companies/{$companyId}/profile", server: self::HEADERS);
        /** @var array<string, mixed> $profile */
        $profile = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $profile['cash_vat'] = true;
        $client->request('PUT', "/api/v1/companies/{$companyId}/profile", server: self::HEADERS, content: json_encode($profile, \JSON_THROW_ON_ERROR));
        $this->issueOfType($client, $companyId, 'FT');

        $year = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');
        $client->request('GET', "/api/v1/companies/{$companyId}/saft?from={$year}-01-01&to={$year}-12-31", server: self::HEADERS);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<CashVATSchemeIndicator>1</CashVATSchemeIndicator>', $client->getInternalResponse()->getContent());
    }

    public function testAnEmptyPeriodStillProducesAValidFile(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/saft?from=2019-01-01&to=2019-12-31", server: self::HEADERS);

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        $xml = $client->getInternalResponse()->getContent();
        $this->assertValidAgainstOfficialSchema($xml);
        self::assertStringNotContainsString('<SourceDocuments', $xml);
    }

    public function testADocumentOutsideThePeriodIsLeftOut(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $this->issueOfType($client, $companyId, 'FT');

        $client->request('GET', "/api/v1/companies/{$companyId}/saft?from=2019-01-01&to=2019-01-31", server: self::HEADERS);

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertStringNotContainsString('<Invoice>', $client->getInternalResponse()->getContent());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badPeriods(): iterable
    {
        yield 'end before start' => ['2026-06-30', '2026-06-01'];
        yield 'crosses New Year' => ['2026-12-15', '2027-01-15'];
        yield 'not a date' => ['yesterday', '2026-12-31'];
        yield 'impossible day' => ['2026-02-30', '2026-03-31'];
        yield 'missing' => ['', ''];
    }

    #[DataProvider('badPeriods')]
    public function testAnInvalidPeriodIsRejected(string $from, string $to): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/saft?from={$from}&to={$to}", server: self::HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testOnlyCompanyMembersWithReportsAccessMayExport(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $client->request('POST', '/api/v1/auth/logout', server: self::HEADERS);

        $client->request('GET', "/api/v1/companies/{$companyId}/saft?from=2026-01-01&to=2026-12-31", server: self::HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function assertValidAgainstOfficialSchema(string $xml): void
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        // libxml2 only speaks XSD 1.0, so validate against the projection of AT's official file
        // (its xs:assert rules are covered by SaftAssertionChecker, which the export itself runs).
        $projected = tempnam(sys_get_temp_dir(), 'saft-test-xsd-');
        file_put_contents((string) $projected, Xsd10Projection::fromFile(__DIR__.'/../../../../docs/legal/SAFTPT1.04_01.xsd'));

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $valid = $document->schemaValidate((string) $projected);
        @unlink((string) $projected);
        $errors = array_map(static fn (\LibXMLError $error): string => \sprintf('line %d: %s', $error->line, trim($error->message)), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        self::assertTrue($valid, "Not valid against SAFTPT1.04_01.xsd:\n".implode("\n", \array_slice($errors, 0, 10)));
    }

    private function text(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): string
    {
        $nodes = null === $context ? $xpath->query($query) : $xpath->query($query, $context);
        self::assertNotFalse($nodes);
        self::assertGreaterThan(0, $nodes->length, \sprintf('No node for %s', $query));
        $node = $nodes->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);

        return $node->textContent;
    }

    /**
     * @return list<string>
     */
    private function texts(\DOMXPath $xpath, string $query, bool $sorted = false): array
    {
        $nodes = $xpath->query($query);
        self::assertNotFalse($nodes);
        $values = [];

        foreach ($nodes as $node) {
            self::assertInstanceOf(\DOMElement::class, $node);
            $values[] = $node->textContent;
        }

        if ($sorted) {
            sort($values);
        }

        return $values;
    }
}
