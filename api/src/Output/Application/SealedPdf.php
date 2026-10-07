<?php

declare(strict_types=1);

namespace App\Output\Application;

use App\Shared\Domain\Output\ArchivedFile;

final class SealedPdf
{
    public function __construct(
        public readonly string $bytes,
        public readonly ArchivedFile $file,
        /** The label printed into the file when it was sealed; known only on the call that sealed it (it is part of the stored bytes, not recorded separately). */
        public readonly ?string $copyLabel,
        public readonly bool $sealedNow,
    ) {
    }
}
