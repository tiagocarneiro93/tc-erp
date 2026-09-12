<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use App\Inventory\Domain\Warehouse;

final class WarehouseView
{
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $address,
        public readonly bool $isDefault,
        public readonly bool $active,
    ) {
    }

    public static function fromEntity(Warehouse $warehouse): self
    {
        return new self(
            $warehouse->id()->toString(),
            $warehouse->code(),
            $warehouse->name(),
            $warehouse->address(),
            $warehouse->isDefault(),
            $warehouse->active(),
        );
    }
}
