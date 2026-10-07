<?php

declare(strict_types=1);

namespace App\Shared\Domain\Company;

/**
 * The company's own identification as other modules need it — today the SAF-T
 * `Header` (docs/plans/phase-3.md task 3.3) and the cash-VAT regime fallback
 * for documents issued before the issuer snapshot carried it. Current
 * company data, not a frozen snapshot: documents carry their own.
 */
final class CompanyFiscalIdentity
{
    public function __construct(
        public readonly string $nif,
        public readonly string $legalName,
        public readonly ?string $commercialName,
        public readonly ?string $address,
        public readonly ?string $postalCode,
        public readonly ?string $city,
        public readonly string $country,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly bool $cashVat,
    ) {
    }
}
