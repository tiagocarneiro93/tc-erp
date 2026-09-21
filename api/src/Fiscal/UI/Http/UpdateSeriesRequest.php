<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * No `document_type` here: it's fixed at creation ({@see Series::update()}).
 */
final class UpdateSeriesRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $code = '',
        public readonly bool $is_training = false,
        #[Assert\Positive]
        public readonly int $first_number = 1,
    ) {
    }
}
