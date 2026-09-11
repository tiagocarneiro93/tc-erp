<?php

declare(strict_types=1);

namespace App\Platform\Application\Query;

final class CompanyMemberView
{
    public function __construct(
        public readonly string $userId,
        public readonly string $email,
        public readonly string $name,
        public readonly string $role,
    ) {
    }
}
