<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use App\Platform\Domain\UserId;

final class InviteUserToCompany
{
    public function __construct(
        public readonly UserId $invitedByUserId,
        public readonly string $email,
        public readonly string $name,
        public readonly string $role,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
