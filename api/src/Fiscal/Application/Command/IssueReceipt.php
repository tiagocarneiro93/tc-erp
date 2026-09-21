<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

final class IssueReceipt
{
    /**
     * @param list<array{document_id: string, amount: string}> $allocations
     */
    public function __construct(
        public readonly string $seriesId,
        public readonly ?string $customerId,
        public readonly string $paymentMethod,
        public readonly array $allocations,
        public readonly string $idempotencyKey,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
