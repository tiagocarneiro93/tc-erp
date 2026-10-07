<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Infrastructure\Saft;

use App\Fiscal\Infrastructure\Saft\XmlReaderSaftSchemaValidator;
use App\Fiscal\Infrastructure\Saft\Xsd10Projection;
use App\Tests\Support\Dom;
use PHPUnit\Framework\TestCase;

final class XmlReaderSaftSchemaValidatorTest extends TestCase
{
    private const OFFICIAL_XSD = __DIR__.'/../../../../../../docs/legal/SAFTPT1.04_01.xsd';
    private const SAMPLE = __DIR__.'/../../../../../../docs/legal/saft-pt-sample-instance.xml';

    public function testTheCopyTheApplicationShipsIsByteIdenticalToTheOfficialSchemaInDocsLegal(): void
    {
        self::assertSame(
            hash_file('sha256', self::OFFICIAL_XSD),
            hash_file('sha256', __DIR__.'/../../../../../resources/saft/SAFTPT1.04_01.xsd'),
            'api/resources/saft/SAFTPT1.04_01.xsd must stay a pristine copy of docs/legal/SAFTPT1.04_01.xsd.',
        );
    }

    public function testATsOwnSampleInstanceIsValid(): void
    {
        // AT's demonstration file (accounting, billing, transport, working documents, payments) —
        // proves the XSD 1.0 projection plus the ported assertions accept a real-world SAF-T file.
        self::assertSame([], $this->validator()->validate(self::SAMPLE));
    }

    public function testTheProjectionIsCompilableByLibxml2AndTheOfficialFileIsNot(): void
    {
        $document = new \DOMDocument();
        $document->loadXML('<AuditFile xmlns="urn:OECD:StandardAuditFile-Tax:PT_1.04_01"/>');
        $projected = tempnam(sys_get_temp_dir(), 'saft-proj-');
        file_put_contents((string) $projected, Xsd10Projection::fromFile(self::OFFICIAL_XSD));

        $previous = libxml_use_internal_errors(true);
        $officialCompiles = @$document->schemaValidate(self::OFFICIAL_XSD);
        libxml_clear_errors();
        // A schema that compiles gives a *validation* verdict ("Header missing"), not a compile error.
        $document->schemaValidate((string) $projected);
        $messages = array_map(static fn (\LibXMLError $error): string => $error->message, libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        @unlink((string) $projected);

        self::assertFalse($officialCompiles);
        self::assertNotEmpty($messages);
        self::assertStringContainsString('Missing child element(s). Expected is ( {urn:OECD:StandardAuditFile-Tax:PT_1.04_01}Header )', implode(' ', $messages));
    }

    public function testTheProjectionKeepsEverythingButTheXsd11Constructs(): void
    {
        $projection = Xsd10Projection::fromFile(self::OFFICIAL_XSD);

        self::assertStringNotContainsString('xs:assert', $projection);
        self::assertStringNotContainsString('minVersion', $projection);
        self::assertStringContainsString('TaxExemptionCode', $projection);
        self::assertStringContainsString('xs:unique', $projection, 'The identity constraints are AT\'s, kept.');
    }

    public function testAStructuralViolationIsReported(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><AuditFile xmlns="urn:OECD:StandardAuditFile-Tax:PT_1.04_01"><MasterFiles/></AuditFile>';

        $errors = $this->validateXml($xml);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('Header', $errors[0]);
    }

    public function testADuplicatedCustomerIdIsReportedByTheIdentityConstraint(): void
    {
        $document = new \DOMDocument();
        $document->load(self::SAMPLE); // libxml honours the file's own encoding declaration
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('s', 'urn:OECD:StandardAuditFile-Tax:PT_1.04_01');
        $customers = Dom::elements($xpath, '//s:MasterFiles/s:Customer');
        self::assertGreaterThan(1, \count($customers));
        $first = $customers[0];
        $first->parentNode?->insertBefore($first->cloneNode(true), $first);

        $errors = $this->validateXml((string) $document->saveXML());

        self::assertStringContainsString('Duplicate key-sequence', implode(' ', $errors));
    }

    public function testABusinessRuleViolationIsReportedEvenThoughTheStructureIsFine(): void
    {
        $document = new \DOMDocument();
        $document->load(self::SAMPLE); // libxml honours the file's own encoding declaration
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('s', 'urn:OECD:StandardAuditFile-Tax:PT_1.04_01');
        // Strip the exemption reason and code from the first exempt line of the sample.
        $reason = Dom::first($xpath, '//s:Invoice/s:Line[s:Tax/s:TaxPercentage=0]/s:TaxExemptionReason');
        $code = Dom::first($xpath, '//s:Invoice/s:Line[s:Tax/s:TaxPercentage=0]/s:TaxExemptionCode');
        self::assertNotNull($reason, 'The sample has no exempt invoice line to mutate.');
        Dom::remove($reason);
        Dom::remove($code);

        $errors = $this->validateXml((string) $document->saveXML());

        self::assertStringContainsString('a zero-tax line needs a TaxExemptionReason', implode(' ', $errors));
    }

    /**
     * @return list<string>
     */
    private function validateXml(string $xml): array
    {
        $path = tempnam(sys_get_temp_dir(), 'saft-validate-');
        file_put_contents((string) $path, $xml);

        try {
            return $this->validator()->validate((string) $path);
        } finally {
            @unlink((string) $path);
        }
    }

    private function validator(): XmlReaderSaftSchemaValidator
    {
        return new XmlReaderSaftSchemaValidator(__DIR__.'/../../../../../resources/saft/SAFTPT1.04_01.xsd');
    }
}
