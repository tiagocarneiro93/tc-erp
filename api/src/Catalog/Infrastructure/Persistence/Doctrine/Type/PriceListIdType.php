<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\PriceListId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class PriceListIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return PriceListId::class;
    }
}
