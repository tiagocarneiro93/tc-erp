<?php

declare(strict_types=1);

namespace App\Shared\Domain\Fiscal;

/**
 * One `document_tax_summary` row: the authoritative per-rate base and tax
 * (technical-scope.md §7.9.5), which is what the printed tax breakdown shows.
 */
final class PrintableTaxSummary
{
    public function __construct(
        public readonly string $region,
        public readonly string $code,
        public readonly string $percentage,
        public readonly string $taxableBase,
        public readonly string $taxAmount,
    ) {
    }
}
