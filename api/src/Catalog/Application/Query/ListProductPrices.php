<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\ProductId;

final class ListProductPrices
{
    public function __construct(public readonly ProductId $productId)
    {
    }
}
