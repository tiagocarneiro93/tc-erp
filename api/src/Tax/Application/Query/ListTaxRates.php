<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

final class ListTaxRates
{
    public function __construct(
        public readonly ?string $region,
        public readonly ?\DateTimeImmutable $asOf,
    ) {
    }
}
