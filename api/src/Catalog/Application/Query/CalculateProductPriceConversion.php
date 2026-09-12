<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\ProductId;

final class CalculateProductPriceConversion
{
    public function __construct(
        public readonly ProductId $productId,
        public readonly string $amount,
        public readonly bool $includesVat,
    ) {
    }
}
