<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

/**
 * An issued `Payments/Payment` (`RG` — never signed, so no hash).
 */
final class SaftReceipt
{
    /**
     * @param list<SaftReceiptLine> $lines
     */
    public function __construct(
        public readonly string $documentNo,
        public readonly string $atcud,
        public readonly string $status,
        public readonly \DateTimeImmutable $statusAt,
        public readonly ?string $statusReason,
        public readonly string $sourceUserId,
        public readonly \DateTimeImmutable $issueDate,
        public readonly \DateTimeImmutable $systemEntryAt,
        public readonly ?string $customerId,
        public readonly string $paymentMethod,
        public readonly string $total,
        public readonly array $lines,
    ) {
    }
}
