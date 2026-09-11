<?php

declare(strict_types=1);

namespace App\Parties\Application\Command;

use App\Parties\Domain\CustomerId;

final class DeactivateCustomer
{
    public function __construct(
        public readonly CustomerId $customerId,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
