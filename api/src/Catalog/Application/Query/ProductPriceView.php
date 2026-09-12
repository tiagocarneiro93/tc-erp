<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\ProductPrice;

final class ProductPriceView
{
    public function __construct(
        public readonly string $priceListId,
        public readonly string $amount,
        public readonly bool $includesVat,
    ) {
    }

    public static function fromEntity(ProductPrice $price): self
    {
        return new self($price->priceListId()->toString(), $price->amount(), $price->includesVat());
    }
}
