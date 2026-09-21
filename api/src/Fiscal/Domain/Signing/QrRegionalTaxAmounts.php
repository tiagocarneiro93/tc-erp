<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Signing;

use App\Shared\Domain\Decimal\Money;

/**
 * `at-qrcode-spec.pdf` §4, one of up to three repeated I/J/K field groups
 * (one per SAF-T `TaxCountryRegion`: PT, PT-AC, PT-MA) — `espaço fiscal`
 * plus the exempt/reduced/intermediate/normal base-and-tax pairs. Every
 * amount but the region itself is optional: the spec only requires the
 * fields for which the document actually has a value (rule (f), "Nos
 * campos opcionais, na ausência de informação não deverá ser criado o
 * respetivo campo").
 */
final class QrRegionalTaxAmounts
{
    public function __construct(
        private readonly string $region,
        private readonly ?Money $exemptBase = null,
        private readonly ?Money $reducedBase = null,
        private readonly ?Money $reducedTax = null,
        private readonly ?Money $intermediateBase = null,
        private readonly ?Money $intermediateTax = null,
        private readonly ?Money $normalBase = null,
        private readonly ?Money $normalTax = null,
    ) {
    }

    public function region(): string
    {
        return $this->region;
    }

    public function exemptBase(): ?Money
    {
        return $this->exemptBase;
    }

    public function reducedBase(): ?Money
    {
        return $this->reducedBase;
    }

    public function reducedTax(): ?Money
    {
        return $this->reducedTax;
    }

    public function intermediateBase(): ?Money
    {
        return $this->intermediateBase;
    }

    public function intermediateTax(): ?Money
    {
        return $this->intermediateTax;
    }

    public function normalBase(): ?Money
    {
        return $this->normalBase;
    }

    public function normalTax(): ?Money
    {
        return $this->normalTax;
    }
}
