<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\WarehouseId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class WarehouseIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return WarehouseId::class;
    }
}
