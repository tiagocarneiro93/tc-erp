<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use App\Platform\Domain\UserId;

final class CreateCompany
{
    public function __construct(
        public readonly UserId $ownerId,
        public readonly string $nif,
        public readonly string $legalName,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
