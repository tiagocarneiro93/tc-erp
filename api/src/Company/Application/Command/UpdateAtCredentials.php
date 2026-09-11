<?php

declare(strict_types=1);

namespace App\Company\Application\Command;

final class UpdateAtCredentials
{
    public function __construct(
        public readonly string $actingUserId,
        public readonly string $subuser,
        public readonly string $password,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
