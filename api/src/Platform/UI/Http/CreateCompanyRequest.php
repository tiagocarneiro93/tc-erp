<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateCompanyRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $nif = '',
        #[Assert\NotBlank]
        public readonly string $legalName = '',
    ) {
    }
}
