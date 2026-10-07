<?php

declare(strict_types=1);

namespace App\Tests\Support\Output;

use App\Output\Domain\DocumentPdfRenderer;
use App\Shared\Domain\Fiscal\PrintableDocument;

final class CountingRenderer implements DocumentPdfRenderer
{
    public int $calls = 0;

    public function render(PrintableDocument $document, string $copyLabel): string
    {
        ++$this->calls;

        return 'pdf:'.$copyLabel;
    }
}
