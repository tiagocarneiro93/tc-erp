<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\CompanyId;

interface ProductComponentRepository
{
    public function find(CompanyId $companyId, ProductId $kitProductId, ProductId $componentProductId): ?ProductComponent;

    /**
     * Ordered by `sortOrder`.
     *
     * @return list<ProductComponent>
     */
    public function findAllForKit(CompanyId $companyId, ProductId $kitProductId): array;

    public function save(ProductComponent $component): void;

    public function remove(ProductComponent $component): void;
}
