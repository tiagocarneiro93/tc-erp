<?php

declare(strict_types=1);

namespace App\Company\Application\Query;

use App\Company\Domain\PaymentTerms;

final class PaymentTermsView
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $days,
        public readonly bool $isDefault,
        public readonly bool $active,
    ) {
    }

    public static function fromEntity(PaymentTerms $paymentTerms): self
    {
        return new self(
            $paymentTerms->id()->toString(),
            $paymentTerms->name(),
            $paymentTerms->days(),
            $paymentTerms->isDefault(),
            $paymentTerms->active(),
        );
    }
}
