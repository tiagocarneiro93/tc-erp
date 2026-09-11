<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;
use App\Tax\Domain\TaxRateId;

final class TaxRateIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return TaxRateId::class;
    }
}
