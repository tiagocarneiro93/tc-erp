<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use App\Platform\Domain\UserId;

final class ChangePassword
{
    public function __construct(
        public readonly UserId $userId,
        public readonly string $currentPassword,
        public readonly string $newPassword,
    ) {
    }
}
