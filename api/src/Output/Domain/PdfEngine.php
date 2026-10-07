<?php

declare(strict_types=1);

namespace App\Output\Domain;

/**
 * HTML in, PDF bytes out — the one thing that differs between a PHP library
 * (mPDF) and a headless-Chromium service (Gotenberg), docs/plans/phase-3.md
 * decision 7. Everything about *what* a document looks like lives in the
 * Twig template, so swapping the engine never touches fiscal content.
 *
 * Implementations must be deterministic: the same HTML and `$timestamp` give
 * the same bytes, byte for byte (no "now", no random document ids leaking into
 * the PDF) — a re-download of an unsealed document must not drift, and a
 * sealed one hashes the exact bytes it signs.
 */
interface PdfEngine
{
    /**
     * @param string $title the PDF's title metadata
     *
     * `$timestamp` is the document's own moment of issue, used for the PDF's creation and modification dates
     */
    public function render(string $html, string $title, \DateTimeImmutable $timestamp): string;
}
