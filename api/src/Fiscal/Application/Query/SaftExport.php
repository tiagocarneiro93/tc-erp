<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\Saft\SaftGenerationSummary;

/**
 * A generated, schema-validated SAF-T file waiting at `$path`, already
 * archived (`stored_files` row `$storedFileId`, docs/plans/phase-3.md task
 * 3.4). The caller owns the local copy from here — serve it and delete it.
 */
final class SaftExport
{
    public function __construct(
        public readonly string $path,
        public readonly string $filename,
        public readonly int $sizeBytes,
        public readonly string $sha256,
        public readonly string $storedFileId,
        public readonly SaftGenerationSummary $summary,
    ) {
    }
}
