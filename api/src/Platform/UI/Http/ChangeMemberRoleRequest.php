<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class ChangeMemberRoleRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $role = '',
    ) {
    }
}
