<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\PriceListId;

final class CreatePriceList
{
    public function __construct(
        public readonly PriceListId $priceListId,
        public readonly string $actingUserId,
        public readonly string $name,
        public readonly bool $defaultIncludesVat,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
