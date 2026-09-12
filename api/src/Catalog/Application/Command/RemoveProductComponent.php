<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\ProductId;

final class RemoveProductComponent
{
    public function __construct(
        public readonly ProductId $kitProductId,
        public readonly ProductId $componentProductId,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
