<?php

declare(strict_types=1);

namespace App\Parties\Application\Command;

use App\Parties\Domain\CustomerId;

final class CreateCustomer
{
    public function __construct(
        public readonly CustomerId $customerId,
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
        public readonly ?string $paymentTermsId,
        public readonly bool $isFinalConsumer,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
