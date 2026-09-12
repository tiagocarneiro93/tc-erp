<?php

declare(strict_types=1);

namespace App\Company\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class PaymentTermsRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $name = '',
        #[Assert\PositiveOrZero]
        public readonly int $days = 0,
        public readonly bool $is_default = false,
    ) {
    }
}
