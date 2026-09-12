<?php

declare(strict_types=1);

namespace App\Parties\Application\Query;

use App\Parties\Domain\Customer;

final class CustomerView
{
    public function __construct(
        public readonly string $id,
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
        public readonly bool $active,
    ) {
    }

    public static function fromEntity(Customer $customer): self
    {
        return new self(
            $customer->id()->toString(),
            $customer->code(),
            $customer->nif(),
            $customer->name(),
            $customer->address(),
            $customer->postalCode(),
            $customer->city(),
            $customer->country(),
            $customer->email(),
            $customer->phone(),
            $customer->paymentTermsId(),
            $customer->isFinalConsumer(),
            $customer->active(),
        );
    }
}
