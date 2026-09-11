<?php

declare(strict_types=1);

namespace App\Parties\Application\Command;

use App\Parties\Domain\SupplierId;

final class DeactivateSupplier
{
    public function __construct(
        public readonly SupplierId $supplierId,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
