<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\CompanyId;

interface PriceListRepository
{
    public function find(CompanyId $companyId, PriceListId $id): ?PriceList;

    /**
     * @return list<PriceList>
     */
    public function findAll(CompanyId $companyId): array;

    public function save(PriceList $priceList): void;
}
