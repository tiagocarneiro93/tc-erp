<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use App\Platform\Domain\UserId;

final class RemoveMember
{
    public function __construct(
        public readonly UserId $actingUserId,
        public readonly UserId $targetUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
