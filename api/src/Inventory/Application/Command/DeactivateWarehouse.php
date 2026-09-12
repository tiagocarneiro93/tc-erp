<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\WarehouseId;

final class DeactivateWarehouse
{
    public function __construct(
        public readonly WarehouseId $warehouseId,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
