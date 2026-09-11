<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Persistence\Doctrine\Type;

use App\Platform\Domain\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class UserIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return UserId::class;
    }
}
