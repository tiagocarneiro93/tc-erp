<?php

declare(strict_types=1);

namespace App\Shared\Domain\AtIntegration;

/**
 * One `document_tax_summary` row: the authoritative per-tax-rate figure
 * (technical-scope.md §7.9.5 — per-line amounts are informational, e.g. under
 * `per_group` rounding), which the e-Fatura `LineSummary` amounts must add up
 * to or AT flags the document as "valores anómalos".
 */
final class AtCommunicableTaxBucket
{
    public function __construct(
        public readonly string $taxRegion,
        public readonly string $taxCode,
        public readonly string $taxPercentage,
        public readonly string $taxableBase,
        public readonly string $taxAmount,
    ) {
    }
}
