<?php

declare(strict_types=1);

namespace App\Tax\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class CalculateRequest
{
    /**
     * @param list<array<string, mixed>> $lines
     */
    public function __construct(
        #[Assert\Choice(['net', 'gross'])]
        public readonly string $pricing_mode = 'net',
        #[Assert\Choice(['per_line', 'per_group'])]
        public readonly string $rounding_method = 'per_line',
        #[Assert\Count(min: 1, minMessage: 'At least one line is required.')]
        public readonly array $lines = [],
        public readonly ?string $global_discount_percent = null,
        public readonly ?string $date = null,
    ) {
    }
}
