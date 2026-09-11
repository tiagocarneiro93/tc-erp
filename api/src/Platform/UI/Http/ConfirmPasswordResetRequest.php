<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class ConfirmPasswordResetRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $token = '',
        #[Assert\NotBlank]
        public readonly string $newPassword = '',
    ) {
    }
}
