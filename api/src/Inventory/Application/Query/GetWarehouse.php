<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use App\Inventory\Domain\WarehouseId;

final class GetWarehouse
{
    public function __construct(public readonly WarehouseId $warehouseId)
    {
    }
}
