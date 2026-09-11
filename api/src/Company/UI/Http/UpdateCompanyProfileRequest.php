<?php

declare(strict_types=1);

namespace App\Company\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateCompanyProfileRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $nif = '',
        #[Assert\NotBlank]
        public readonly string $legal_name = '',
        public readonly ?string $commercial_name = null,
        public readonly ?string $address = null,
        public readonly ?string $postal_code = null,
        public readonly ?string $city = null,
        #[Assert\NotBlank]
        public readonly string $country = '',
        public readonly ?string $share_capital = null,
        public readonly ?string $registry_office = null,
        #[Assert\Email]
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $logo_key = null,
        #[Assert\NotBlank]
        public readonly string $fiscal_region = '',
        #[Assert\NotBlank]
        public readonly string $vat_regime = '',
        public readonly bool $cash_vat = false,
    ) {
    }
}
