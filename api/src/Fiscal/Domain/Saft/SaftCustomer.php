<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

/**
 * A `MasterFiles/Customer` entry, taken from the customer data frozen into
 * the documents themselves (`documents.customer_snapshot`) — never read live
 * from Parties, which Fiscal may not touch and which can no longer change in
 * ways that matter once a document is issued (Despacho 8632/2014).
 */
final class SaftCustomer
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $taxId,
        public readonly string $name,
        public readonly ?string $address,
        public readonly ?string $postalCode,
        public readonly ?string $city,
        public readonly ?string $country,
    ) {
    }
}
