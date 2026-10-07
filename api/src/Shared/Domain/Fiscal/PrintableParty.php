<?php

declare(strict_types=1);

namespace App\Shared\Domain\Fiscal;

/**
 * Issuer or customer as frozen into the document at issuance — what a later
 * reprint must show whatever has changed since (Despacho 8632/2014 §2.2.15).
 */
final class PrintableParty
{
    public function __construct(
        public readonly string $name,
        public readonly string $taxId,
        public readonly ?string $address,
        public readonly ?string $postalCode,
        public readonly ?string $city,
        public readonly ?string $country,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
    ) {
    }
}
