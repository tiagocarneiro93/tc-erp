<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Infrastructure\Saft;

use App\Fiscal\Infrastructure\Saft\SaftAssertionChecker;
use PHPUnit\Framework\TestCase;

/**
 * One passing and one failing document for every `xs:assert` of
 * `SAFTPT1.04_01.xsd` that {@see SaftAssertionChecker} stands in for.
 */
final class SaftAssertionCheckerTest extends TestCase
{
    private const NS = 'urn:OECD:StandardAuditFile-Tax:PT_1.04_01';

    public function testACorrectlyTaxedAndAnExemptLineBothPass(): void
    {
        $errors = $this->check($this->invoice(
            $this->line(1, '<TaxPercentage>23.00</TaxPercentage>', ''),
            $this->line(2, '<TaxPercentage>0.00</TaxPercentage>', '<TaxExemptionReason>Isento artigo 9.º</TaxExemptionReason><TaxExemptionCode>M07</TaxExemptionCode>'),
        ));

        self::assertSame([], $errors);
    }

    public function testAZeroTaxLineWithoutAnExemptionReasonFails(): void
    {
        $errors = $this->check($this->invoice($this->line(1, '<TaxPercentage>0.00</TaxPercentage>', '')));

        self::assertCount(1, $errors);
        self::assertStringContainsString('FT A/1 line 1: a zero-tax line needs a TaxExemptionReason', $errors[0]);
    }

    public function testATaxedLineWithAnExemptionReasonFails(): void
    {
        $errors = $this->check($this->invoice($this->line(1, '<TaxPercentage>23.00</TaxPercentage>', '<TaxExemptionReason>Isento artigo 9.º</TaxExemptionReason><TaxExemptionCode>M07</TaxExemptionCode>')));

        self::assertCount(1, $errors);
        self::assertStringContainsString('only allowed when the tax is zero', $errors[0]);
    }

    public function testAFixedTaxAmountFollowsTheSameRule(): void
    {
        $errors = $this->check($this->invoice($this->line(1, '<TaxAmount>0.00</TaxAmount>', '')));

        self::assertCount(1, $errors);
        self::assertStringContainsString('a zero-tax line needs a TaxExemptionReason', $errors[0]);
    }

    public function testTheExemptionReasonAndCodeComeTogether(): void
    {
        $onlyReason = $this->check($this->invoice($this->line(1, '<TaxPercentage>0.00</TaxPercentage>', '<TaxExemptionReason>Isento artigo 9.º</TaxExemptionReason>')));
        $onlyCode = $this->check($this->invoice($this->line(1, '<TaxPercentage>0.00</TaxPercentage>', '<TaxExemptionCode>M07</TaxExemptionCode>')));

        self::assertStringContainsString('must be given together', implode(' ', $onlyReason));
        self::assertStringContainsString('must be given together', implode(' ', $onlyCode));
    }

    public function testALineWithATaxBaseMustHaveZeroUnitPriceAndAmount(): void
    {
        $line = '<Line><LineNumber>1</LineNumber><TaxBase>10.00</TaxBase><UnitPrice>5.00</UnitPrice><CreditAmount>0.00</CreditAmount><Tax><TaxPercentage>23.00</TaxPercentage></Tax></Line>';

        $errors = $this->check($this->invoice($line));

        self::assertCount(1, $errors);
        self::assertStringContainsString('must have UnitPrice = 0', $errors[0]);
    }

    public function testAnRcPaymentNeedsTaxButAnRgPaymentDoesNot(): void
    {
        $rg = $this->wrap('<SourceDocuments><Payments><Payment><PaymentRefNo>RG A/1</PaymentRefNo><PaymentType>RG</PaymentType><Line><LineNumber>1</LineNumber><CreditAmount>5.00</CreditAmount></Line></Payment></Payments></SourceDocuments>');
        $rc = $this->wrap('<SourceDocuments><Payments><Payment><PaymentRefNo>RC A/1</PaymentRefNo><PaymentType>RC</PaymentType><Line><LineNumber>1</LineNumber><CreditAmount>5.00</CreditAmount></Line></Payment></Payments></SourceDocuments>');

        self::assertSame([], $this->checkXml($rg));
        self::assertStringContainsString('RC must carry Tax', implode(' ', $this->checkXml($rc)));
    }

    public function testEveryDocumentIsCheckedNotJustTheFirst(): void
    {
        $bad = $this->line(1, '<TaxPercentage>0.00</TaxPercentage>', '');
        $xml = $this->wrap('<SourceDocuments><SalesInvoices>'
            .'<Invoice><InvoiceNo>FT A/1</InvoiceNo>'.$bad.'</Invoice>'
            .'<Invoice><InvoiceNo>FT A/2</InvoiceNo>'.$this->line(1, '<TaxPercentage>23.00</TaxPercentage>', '').'</Invoice>'
            .'<Invoice><InvoiceNo>FT A/3</InvoiceNo>'.$bad.'</Invoice>'
            .'</SalesInvoices></SourceDocuments>');

        $errors = $this->checkXml($xml);

        self::assertCount(2, $errors);
        self::assertStringContainsString('FT A/1', $errors[0]);
        self::assertStringContainsString('FT A/3', $errors[1]);
    }

    /**
     * @return list<string>
     */
    private function check(string $invoiceXml): array
    {
        return $this->checkXml($this->wrap('<SourceDocuments><SalesInvoices>'.$invoiceXml.'</SalesInvoices></SourceDocuments>'));
    }

    /**
     * @return list<string>
     */
    private function checkXml(string $xml): array
    {
        $path = tempnam(sys_get_temp_dir(), 'saft-assert-');
        file_put_contents((string) $path, $xml);

        try {
            return (new SaftAssertionChecker())->check((string) $path);
        } finally {
            @unlink((string) $path);
        }
    }

    private function wrap(string $inner): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><AuditFile xmlns="'.self::NS.'">'.$inner.'</AuditFile>';
    }

    private function invoice(string ...$lines): string
    {
        return '<Invoice><InvoiceNo>FT A/1</InvoiceNo>'.implode('', $lines).'</Invoice>';
    }

    private function line(int $number, string $taxField, string $exemption): string
    {
        return \sprintf('<Line><LineNumber>%d</LineNumber><CreditAmount>10.00</CreditAmount><Tax><TaxType>IVA</TaxType>%s</Tax>%s</Line>', $number, $taxField, $exemption);
    }
}
