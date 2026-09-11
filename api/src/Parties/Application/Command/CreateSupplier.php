<?php

declare(strict_types=1);

namespace App\Parties\Application\Command;

use App\Parties\Domain\SupplierId;

final class CreateSupplier
{
    public function __construct(
        public readonly SupplierId $supplierId,
        public readonly string $actingUserId,
        public readonly string $code,
        public readonly string $nif,
        public readonly string $name,
        public readonly ?string $address,
        public readonly ?string $postalCode,
        public readonly ?string $city,
        public readonly string $country,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?int $paymentTermsDays,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
