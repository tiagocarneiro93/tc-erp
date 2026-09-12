<?php

declare(strict_types=1);

namespace App\Parties\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class CustomerRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $code = '',
        #[Assert\NotBlank]
        public readonly string $nif = '',
        #[Assert\NotBlank]
        public readonly string $name = '',
        public readonly ?string $address = null,
        public readonly ?string $postal_code = null,
        public readonly ?string $city = null,
        #[Assert\NotBlank]
        public readonly string $country = '',
        #[Assert\Email]
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $payment_terms_id = null,
        // Create-only: fixed once the customer exists, ignored by the
        // update endpoint (Customer::update() has no such parameter).
        public readonly bool $is_final_consumer = false,
    ) {
    }
}
