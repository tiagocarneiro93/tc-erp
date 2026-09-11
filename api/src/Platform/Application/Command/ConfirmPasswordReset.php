<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

final class ConfirmPasswordReset
{
    public function __construct(
        public readonly string $token,
        public readonly string $newPassword,
    ) {
    }
}
