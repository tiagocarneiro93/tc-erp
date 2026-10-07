<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

final class SaftGenerationSummary
{
    public function __construct(
        public readonly int $invoices,
        public readonly int $workDocuments,
        public readonly int $payments,
        public readonly int $customers,
        public readonly int $products,
    ) {
    }
}
