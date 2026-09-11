<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class InviteUserRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email = '',
        #[Assert\NotBlank]
        public readonly string $name = '',
        #[Assert\NotBlank]
        public readonly string $role = '',
    ) {
    }
}
