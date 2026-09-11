<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Persistence\Doctrine\Type;

use App\Platform\Domain\CompanyId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class CompanyIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return CompanyId::class;
    }
}
