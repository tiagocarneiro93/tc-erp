<?php

declare(strict_types=1);

namespace App\Output\Domain;

use App\Shared\Domain\Fiscal\PrintableDocument;

/**
 * technical-scope.md §7.8: renders an issued document to PDF from its stored
 * data only. `$copyLabel` is the mention that tells original from copy
 * (Despacho 8632/2014 §2.2.15) — part of the rendered bytes, so the same
 * document with the same label renders identically every time.
 */
interface DocumentPdfRenderer
{
    public function render(PrintableDocument $document, string $copyLabel): string;
}
