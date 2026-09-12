<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

final class PriceConversionResult
{
    public function __construct(
        public readonly string $amount,
        public readonly bool $includesVat,
        public readonly string $convertedAmount,
        public readonly bool $convertedIncludesVat,
    ) {
    }
}
