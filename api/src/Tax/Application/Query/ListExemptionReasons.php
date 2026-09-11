<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

final class ListExemptionReasons
{
    public function __construct(
        public readonly ?\DateTimeImmutable $asOf,
    ) {
    }
}
