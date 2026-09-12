<?php

declare(strict_types=1);

namespace App\Shared\Domain\Tax;

/**
 * Cross-module port (docs/decisions/0004's pattern): lets a module other
 * than Tax convert a price between VAT-inclusive and -exclusive using a
 * `tax_rate_id`'s percentage (§7.9.8's `Tax\Domain\VatConversion`), without
 * depending on `Tax\Domain` directly. Used by Catalog's
 * `POST /products/{id}/prices/calculate` (task 1.7).
 */
interface TaxRateConverter
{
    /**
     * Converts $amount — entered as VAT-inclusive if $includesVat is true,
     * VAT-exclusive otherwise — into its other-mode value, using
     * $taxRateId's percentage. Returns null if $taxRateId doesn't exist.
     *
     * @throws \Brick\Math\Exception\MathException if $amount is not a valid decimal string
     */
    public function convertToOtherMode(string $taxRateId, string $amount, bool $includesVat): ?string;
}
