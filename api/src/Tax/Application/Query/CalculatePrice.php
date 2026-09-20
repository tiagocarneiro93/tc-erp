<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

final class CalculatePrice
{
    /**
     * Raw, unvalidated request data — {@see CalculatePriceHandler} parses
     * and validates each line, reporting the offending index on failure.
     *
     * @param list<array<string, mixed>> $lines
     */
    public function __construct(
        public readonly string $pricingMode,
        public readonly string $roundingMethod,
        public readonly array $lines,
        public readonly ?string $globalDiscountPercent,
        public readonly \DateTimeImmutable $date,
    ) {
    }
}
