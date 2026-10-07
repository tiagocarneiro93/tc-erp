<?php

declare(strict_types=1);

namespace App\Output\Application;

use App\Shared\Domain\Fiscal\PrintableDocument;
use App\Shared\Domain\Output\ArchivedFile;

final class SealedPdf
{
    public function __construct(
        public readonly string $bytes,
        public readonly ArchivedFile $file,
        /** The label printed into the file when it was sealed. */
        public readonly string $copyLabel,
        public readonly bool $sealedNow,
        public readonly PrintableDocument $document,
    ) {
    }
}
