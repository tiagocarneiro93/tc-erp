<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ProductFamilyId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class ProductFamilyIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return ProductFamilyId::class;
    }
}
