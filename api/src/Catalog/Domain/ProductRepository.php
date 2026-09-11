<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\CompanyId;

interface ProductRepository
{
    public function find(CompanyId $companyId, ProductId $id): ?Product;

    /**
     * Ordered ascending by id, for {@see \App\Shared\Domain\Http\CursorPaginator}.
     * `$search` matches code, description or barcode.
     *
     * @return list<Product>
     */
    public function search(
        CompanyId $companyId,
        ?string $search,
        ?ProductFamilyId $familyId,
        ?bool $active,
        ?bool $trackStock,
    ): array;

    public function save(Product $product): void;
}
