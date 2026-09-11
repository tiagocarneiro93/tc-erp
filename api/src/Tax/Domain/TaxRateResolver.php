<?php

declare(strict_types=1);

namespace App\Tax\Domain;

use App\Tax\Domain\Exception\NoApplicableTaxRate;

/**
 * The single lookup for "which rate applies" (technical-scope.md §7.9.2 —
 * one calculator, no second implementation elsewhere). Phase 2's
 * PriceCalculator calls this; nothing computes VAT without going through it.
 */
final class TaxRateResolver
{
    public function __construct(
        private readonly TaxRateRepository $rates,
    ) {
    }

    public function resolve(string $region, string $code, \DateTimeImmutable $date): TaxRate
    {
        $rate = $this->rates->findApplicable($region, $code, $date);

        if (null === $rate) {
            throw new NoApplicableTaxRate($region, $code, $date);
        }

        return $rate;
    }
}
