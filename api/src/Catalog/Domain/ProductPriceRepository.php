<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\CompanyId;

interface ProductPriceRepository
{
    public function find(CompanyId $companyId, ProductId $productId, PriceListId $priceListId): ?ProductPrice;

    /**
     * Every price list's price for one product.
     *
     * @return list<ProductPrice>
     */
    public function findAllForProduct(CompanyId $companyId, ProductId $productId): array;

    public function save(ProductPrice $price): void;
}
