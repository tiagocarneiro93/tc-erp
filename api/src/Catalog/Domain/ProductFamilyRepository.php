<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\CompanyId;

interface ProductFamilyRepository
{
    public function find(CompanyId $companyId, ProductFamilyId $id): ?ProductFamily;

    /**
     * @return list<ProductFamily>
     */
    public function findAll(CompanyId $companyId): array;

    public function save(ProductFamily $family): void;
}
