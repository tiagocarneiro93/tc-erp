<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class ActivateSeriesRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $validation_code = '',
    ) {
    }
}
