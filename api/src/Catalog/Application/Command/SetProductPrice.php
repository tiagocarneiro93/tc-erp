<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\PriceListId;
use App\Catalog\Domain\ProductId;

final class SetProductPrice
{
    public function __construct(
        public readonly ProductId $productId,
        public readonly PriceListId $priceListId,
        public readonly string $actingUserId,
        public readonly string $amount,
        public readonly bool $includesVat,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
