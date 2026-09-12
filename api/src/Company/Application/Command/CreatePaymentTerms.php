<?php

declare(strict_types=1);

namespace App\Company\Application\Command;

use App\Company\Domain\PaymentTermsId;

final class CreatePaymentTerms
{
    public function __construct(
        public readonly PaymentTermsId $paymentTermsId,
        public readonly string $actingUserId,
        public readonly string $name,
        public readonly int $days,
        public readonly bool $isDefault,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
