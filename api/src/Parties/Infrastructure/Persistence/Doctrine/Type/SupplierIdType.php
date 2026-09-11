<?php

declare(strict_types=1);

namespace App\Parties\Infrastructure\Persistence\Doctrine\Type;

use App\Parties\Domain\SupplierId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class SupplierIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return SupplierId::class;
    }
}
