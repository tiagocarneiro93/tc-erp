<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ProductId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class ProductIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return ProductId::class;
    }
}
