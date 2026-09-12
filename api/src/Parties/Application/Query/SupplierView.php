<?php

declare(strict_types=1);

namespace App\Parties\Application\Query;

use App\Parties\Domain\Supplier;

final class SupplierView
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
        public readonly bool $active,
    ) {
    }

    public static function fromEntity(Supplier $supplier): self
    {
        return new self(
            $supplier->id()->toString(),
            $supplier->code(),
            $supplier->nif(),
            $supplier->name(),
            $supplier->address(),
            $supplier->postalCode(),
            $supplier->city(),
            $supplier->country(),
            $supplier->email(),
            $supplier->phone(),
            $supplier->paymentTermsId(),
            $supplier->active(),
        );
    }
}
