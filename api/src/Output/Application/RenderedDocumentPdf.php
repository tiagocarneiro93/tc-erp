<?php

declare(strict_types=1);

namespace App\Output\Application;

final class RenderedDocumentPdf
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $filename,
        public readonly string $copyLabel,
    ) {
    }
}
