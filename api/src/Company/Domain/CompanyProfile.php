<?php

declare(strict_types=1);

namespace App\Company\Domain;

use App\Company\Domain\Exception\InvalidFiscalRegion;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Decimal\Money;
use App\Shared\Domain\Nif;

/**
 * technical-scope.md §6.2. One row per company, created with sane defaults
 * by `CreateDefaultCompanyProfileOnCompanyRegistered` and edited afterwards
 * through `UpdateCompanyProfile` — never absent for a company that exists.
 */
final class CompanyProfile
{
    /**
     * PT|PT-AC|PT-MA, matching `tax_rates.region` (task 1.2) — this is the
     * region whose rates apply to this company's documents.
     */
    private const VALID_FISCAL_REGIONS = ['PT', 'PT-AC', 'PT-MA'];

    private function __construct(
        private readonly CompanyId $companyId,
        private Nif $nif,
        private string $legalName,
        private ?string $commercialName,
        private ?string $address,
        private ?string $postalCode,
        private ?string $city,
        private string $country,
        private ?Money $shareCapital,
        private ?string $registryOffice,
        private ?string $email,
        private ?string $phone,
        private ?string $logoKey,
        private string $fiscalRegion,
        private string $vatRegime,
        private bool $cashVat,
    ) {
    }

    /**
     * Pre-filled with what `CompanyRegistered` already knows (NIF, legal
     * name); everything else defaults to mainland Portugal, the "normal"
     * VAT regime and no cash-VAT scheme, edited later via `updateProfile()`.
     */
    public static function createDefault(CompanyId $companyId, Nif $nif, string $legalName): self
    {
        return new self(
            $companyId,
            $nif,
            $legalName,
            null,
            null,
            null,
            null,
            'PT',
            null,
            null,
            null,
            null,
            null,
            'PT',
            'normal',
            false,
        );
    }

    public function updateProfile(
        Nif $nif,
        string $legalName,
        ?string $commercialName,
        ?string $address,
        ?string $postalCode,
        ?string $city,
        string $country,
        ?Money $shareCapital,
        ?string $registryOffice,
        ?string $email,
        ?string $phone,
        ?string $logoKey,
        string $fiscalRegion,
        string $vatRegime,
        bool $cashVat,
    ): void {
        if (!\in_array($fiscalRegion, self::VALID_FISCAL_REGIONS, true)) {
            throw new InvalidFiscalRegion($fiscalRegion);
        }

        $this->nif = $nif;
        $this->legalName = $legalName;
        $this->commercialName = $commercialName;
        $this->address = $address;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->country = $country;
        $this->shareCapital = $shareCapital;
        $this->registryOffice = $registryOffice;
        $this->email = $email;
        $this->phone = $phone;
        $this->logoKey = $logoKey;
        $this->fiscalRegion = $fiscalRegion;
        $this->vatRegime = $vatRegime;
        $this->cashVat = $cashVat;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function nif(): Nif
    {
        return $this->nif;
    }

    public function legalName(): string
    {
        return $this->legalName;
    }

    public function commercialName(): ?string
    {
        return $this->commercialName;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    public function city(): ?string
    {
        return $this->city;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function shareCapital(): ?Money
    {
        return $this->shareCapital;
    }

    public function registryOffice(): ?string
    {
        return $this->registryOffice;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function logoKey(): ?string
    {
        return $this->logoKey;
    }

    public function fiscalRegion(): string
    {
        return $this->fiscalRegion;
    }

    public function vatRegime(): string
    {
        return $this->vatRegime;
    }

    public function cashVat(): bool
    {
        return $this->cashVat;
    }
}
