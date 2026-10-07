<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Pdf;

use App\Output\Domain\PdfEngine;
use App\Output\Domain\PdfRenderingFailed;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * mPDF: a PHP library, no extra service to run — the engine the scope's own
 * wording leans to ("simpler operations", technical-scope.md §7.8). Limits to
 * know when writing templates: its CSS is CSS 2.1 plus some extras (tables,
 * floats, no flexbox/grid), which the shipped template respects so the very
 * same HTML renders in {@see GotenbergPdfEngine} too.
 *
 * Determinism comes from {@see PdfDeterminism}, applied to mPDF's output.
 */
final class MpdfPdfEngine implements PdfEngine
{
    public function __construct(private readonly string $tempDirectory)
    {
    }

    public function render(string $html, string $title, \DateTimeImmutable $timestamp): string
    {
        if (!is_dir($this->tempDirectory) && !@mkdir($this->tempDirectory, 0o775, true) && !is_dir($this->tempDirectory)) {
            throw new PdfRenderingFailed(\sprintf('The mPDF temporary directory "%s" cannot be created.', $this->tempDirectory));
        }

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 12,
                'margin_bottom' => 14,
                'default_font' => 'dejavusans',
                'default_font_size' => 9,
                'tempDir' => $this->tempDirectory,
            ]);
            $mpdf->SetTitle($title);
            $mpdf->SetCreator('tc-erp');
            $mpdf->SetAuthor('tc-erp');
            $mpdf->WriteHTML($html);

            $pdf = $mpdf->Output('', Destination::STRING_RETURN);

            if (!\is_string($pdf) || '' === $pdf) {
                throw new PdfRenderingFailed('mPDF returned no content.');
            }
        } catch (\Throwable $e) {
            throw new PdfRenderingFailed('mPDF could not render the document: '.$e->getMessage(), 0, $e);
        }

        return PdfDeterminism::normalize($pdf, $timestamp);
    }
}
