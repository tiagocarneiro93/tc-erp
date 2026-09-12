<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Shared\Domain\CompanyId;

interface WarehouseRepository
{
    public function find(CompanyId $companyId, WarehouseId $id): ?Warehouse;

    public function findDefault(CompanyId $companyId): ?Warehouse;

    /**
     * Ordered ascending by code.
     *
     * @return list<Warehouse>
     */
    public function findAll(CompanyId $companyId): array;

    public function save(Warehouse $warehouse): void;
}
