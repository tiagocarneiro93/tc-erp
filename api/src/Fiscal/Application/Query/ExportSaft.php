<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

final class ExportSaft
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
    }
}
