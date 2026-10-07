<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

/**
 * A `TaxTable/TaxTableEntry`: one (region, code, percentage) that actually
 * appears on a document of the period.
 */
final class SaftTaxEntry
{
    public function __construct(
        public readonly string $region,
        public readonly string $code,
        public readonly string $percentage,
    ) {
    }
}
