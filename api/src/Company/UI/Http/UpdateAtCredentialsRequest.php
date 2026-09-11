<?php

declare(strict_types=1);

namespace App\Company\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAtCredentialsRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $subuser = '',
        #[Assert\NotBlank]
        public readonly string $password = '',
    ) {
    }
}
