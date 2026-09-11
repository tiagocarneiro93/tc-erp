<?php

declare(strict_types=1);

namespace App\Parties\Domain;

use App\Shared\Domain\CompanyId;

interface SupplierRepository
{
    public function find(CompanyId $companyId, SupplierId $id): ?Supplier;

    /**
     * @return list<Supplier>
     */
    public function search(CompanyId $companyId, ?string $search): array;

    public function save(Supplier $supplier): void;
}
