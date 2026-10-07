<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Infrastructure\Pdf;

use App\Output\Domain\PdfEngine;
use App\Output\Domain\PdfRenderingFailed;
use App\Output\Infrastructure\Pdf\QrCodeImage;
use App\Output\Infrastructure\Pdf\TwigDocumentPdfRenderer;
use App\Shared\Domain\Fiscal\PrintableParty;
use App\Tests\Support\PrintableDocuments;
use PHPUnit\Framework\TestCase;

/**
 * What the template puts on the page, asserted on the HTML it hands the
 * engine — one assertion per legal mention, with the source it comes from.
 */
final class TwigDocumentPdfRendererTest extends TestCase
{
    public function testAnInvoiceCarriesItsLegalMentions(): void
    {
        $html = $this->html(PrintableDocuments::invoice());

        self::assertStringContainsString('Empresa Exportadora, Lda', $html);
        self::assertStringContainsString('NIF 508025090', $html);
        self::assertStringContainsString('Fatura', $html);
        self::assertStringContainsString('FT 2026A/1', $html);
        self::assertStringContainsString('ATCUD: CSDF7T5H-1', $html, 'Portaria 195/2020');
        self::assertStringContainsString('2026-10-07', $html, 'Despacho 8632/2014 §2.2.4: AAAA-MM-DD');
        self::assertStringContainsString('AbCd-Processado por programa certificado n.º 0000/AT', $html, 'Despacho 8632/2014 §2.2.2');
        self::assertStringContainsString('data:image/svg+xml;base64,', $html, 'the QR code');
        self::assertStringContainsString('NIF 123456789', $html);
        self::assertStringNotContainsString('não serve de fatura', $html);
        self::assertStringNotContainsString('Formação', $html);
        self::assertStringNotContainsString('ANULADO', $html);
    }

    public function testAWorkingDocumentSaysItIsNotAnInvoice(): void
    {
        $html = $this->html(PrintableDocuments::invoice('OR', 'Orçamento', notAnInvoiceMention: 'Este documento não serve de fatura'));

        self::assertStringContainsString('Este documento não serve de fatura', $html, 'Despacho 8632/2014 §1.2');
        self::assertStringContainsString('Orçamento', $html);
    }

    public function testAnExemptLineShowsItsWordingNextToItsCode(): void
    {
        $html = $this->html(PrintableDocuments::invoice());

        self::assertStringContainsString('[M07]', $html, 'Despacho §2.2.14: the line is tied to its reason');
        self::assertStringContainsString('Isento artigo 9.º do CIVA', $html);
    }

    public function testAmountsArePtPtFormattedFromStoredValues(): void
    {
        $html = $this->html(PrintableDocuments::invoice());

        self::assertStringContainsString('1&nbsp;307,00&nbsp;€', $html);
        self::assertStringContainsString('207,00&nbsp;€', $html);
        self::assertStringContainsString('23,00%', $html);
        self::assertStringContainsString('500,00', $html);
    }

    public function testTheCopyLabelIsPrinted(): void
    {
        self::assertStringContainsString('Original', $this->html(PrintableDocuments::invoice(), 'Original'));
        self::assertStringContainsString('Triplicado', $this->html(PrintableDocuments::invoice(), 'Triplicado'));
    }

    public function testATrainingDocumentShowsTheSoftwareProducersHeaderAndTheTrainingMention(): void
    {
        $html = $this->html(PrintableDocuments::invoice(training: true));

        self::assertStringContainsString('Documento emitido para fins de Formação', $html, 'Despacho 8632/2014 §1.5');
        self::assertStringContainsString('TCWeb', $html, 'the software producer, not the client company, heads the document');
        self::assertStringNotContainsString('Empresa Exportadora', $html);
    }

    public function testACancelledDocumentIsMarked(): void
    {
        $html = $this->html(PrintableDocuments::invoice(status: 'A'));

        self::assertStringContainsString('DOCUMENTO ANULADO', $html);
        self::assertStringContainsString('Emitida por engano', $html);
    }

    public function testTextFromStoredDataIsEscaped(): void
    {
        $html = $this->html(PrintableDocuments::invoice(customerName: '<script>alert(1)</script> & Filhos'));

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; Filhos', $html);
    }

    public function testTheSameDocumentAlwaysRendersTheSameHtml(): void
    {
        self::assertSame($this->html(PrintableDocuments::invoice()), $this->html(PrintableDocuments::invoice()));
    }

    public function testATemplateVersionThatNoLongerExistsIsRefused(): void
    {
        $this->expectException(PdfRenderingFailed::class);
        $this->expectExceptionMessage('v99');

        $this->html(PrintableDocuments::invoice(templateVersion: 'v99'));
    }

    public function testATemplateVersionThatIsNotAVersionIsRefused(): void
    {
        $this->expectException(PdfRenderingFailed::class);

        $this->html(PrintableDocuments::invoice(templateVersion: '../../etc'));
    }

    public function testTheEnginesTitleAndTimestampComeFromTheDocument(): void
    {
        $engine = new CapturingEngine();
        $renderer = new TwigDocumentPdfRenderer(__DIR__.'/../../../../../templates/pdf', $engine, new QrCodeImage(), new PrintableParty('TCWeb', '999999990', null, null, null, 'PT'));

        $renderer->render(PrintableDocuments::invoice(), 'Original');

        self::assertSame('Fatura FT 2026A/1', $engine->title);
        self::assertSame('2026-10-07T10:11:12+00:00', $engine->timestamp?->format('c'));
    }

    private function html(\App\Shared\Domain\Fiscal\PrintableDocument $document, string $copyLabel = 'Original'): string
    {
        $engine = new CapturingEngine();
        $renderer = new TwigDocumentPdfRenderer(__DIR__.'/../../../../../templates/pdf', $engine, new QrCodeImage(), new PrintableParty('TCWeb', '999999990', null, null, null, 'PT'));
        $renderer->render($document, $copyLabel);

        return $engine->html;
    }
}

final class CapturingEngine implements PdfEngine
{
    public string $html = '';
    public string $title = '';
    public ?\DateTimeImmutable $timestamp = null;

    public function render(string $html, string $title, \DateTimeImmutable $timestamp): string
    {
        $this->html = $html;
        $this->title = $title;
        $this->timestamp = $timestamp;

        return '%PDF-captured';
    }
}
