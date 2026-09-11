<?php

declare(strict_types=1);

namespace App\Company\Application\Query;

final class CompanyProfileView
{
    public function __construct(
        public readonly string $nif,
        public readonly string $legalName,
        public readonly ?string $commercialName,
        public readonly ?string $address,
        public readonly ?string $postalCode,
        public readonly ?string $city,
        public readonly string $country,
        public readonly ?string $shareCapital,
        public readonly ?string $registryOffice,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $logoKey,
        public readonly string $fiscalRegion,
        public readonly string $vatRegime,
        public readonly bool $cashVat,
    ) {
    }
}
