<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

final class SaftReceiptLine
{
    public function __construct(
        public readonly int $lineNumber,
        public readonly string $invoiceNo,
        public readonly \DateTimeImmutable $invoiceDate,
        public readonly string $amount,
        public readonly string $settlementAmount,
    ) {
    }
}
