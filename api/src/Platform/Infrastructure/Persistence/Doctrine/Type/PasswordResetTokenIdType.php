<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Persistence\Doctrine\Type;

use App\Platform\Domain\PasswordResetTokenId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractUuidIdType;

final class PasswordResetTokenIdType extends AbstractUuidIdType
{
    protected function idClass(): string
    {
        return PasswordResetTokenId::class;
    }
}
