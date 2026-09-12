<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\WarehouseId;

final class UpdateWarehouse
{
    public function __construct(
        public readonly WarehouseId $warehouseId,
        public readonly string $actingUserId,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $address,
        public readonly bool $isDefault,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
