<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use App\Platform\Domain\PasswordPolicy;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangePasswordRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $currentPassword = '',
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: PasswordPolicy::REGEX, message: 'The password does not meet the minimum requirements.')]
        public readonly string $newPassword = '',
    ) {
    }
}
