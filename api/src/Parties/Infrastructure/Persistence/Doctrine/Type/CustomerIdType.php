<?php

declare(strict_types=1);

namespace App\Parties\Infrastructure\Persistence\Doctrine\Type;

use App\Parties\Domain\CustomerId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class CustomerIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return CustomerId::class;
    }
}
