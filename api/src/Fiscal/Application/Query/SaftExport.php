<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\Saft\SaftGenerationSummary;

/**
 * A generated, schema-validated SAF-T file waiting at `$path`. The caller
 * owns the file from here — serve it and delete it (docs/plans/phase-3.md
 * task 3.4 will persist it as a `stored_files` row instead).
 */
final class SaftExport
{
    public function __construct(
        public readonly string $path,
        public readonly string $filename,
        public readonly int $sizeBytes,
        public readonly string $sha256,
        public readonly SaftGenerationSummary $summary,
    ) {
    }
}
