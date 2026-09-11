<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Persistence\Doctrine\Type;

use App\Platform\Domain\ApiTokenId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class ApiTokenIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return ApiTokenId::class;
    }
}
