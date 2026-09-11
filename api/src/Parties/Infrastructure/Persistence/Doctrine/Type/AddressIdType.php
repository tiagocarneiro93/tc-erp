<?php

declare(strict_types=1);

namespace App\Parties\Infrastructure\Persistence\Doctrine\Type;

use App\Parties\Domain\AddressId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class AddressIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return AddressId::class;
    }
}
