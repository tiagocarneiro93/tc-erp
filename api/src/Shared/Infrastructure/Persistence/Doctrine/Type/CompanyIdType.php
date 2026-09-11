<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\CompanyId;

final class CompanyIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return CompanyId::class;
    }
}
