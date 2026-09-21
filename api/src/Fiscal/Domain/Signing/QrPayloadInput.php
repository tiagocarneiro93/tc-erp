<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Signing;

use App\Shared\Domain\Decimal\Money;

/**
 * Every field {@see QrPayloadBuilder} needs, fields A–S of
 * `at-qrcode-spec.pdf` §4. `regionalTaxAmounts` holds up to 3 entries
 * (I/J/K groups) and must be empty when `taxNotIndicated` is true — the
 * spec's §3(h)/§4 field-I1 special case for a movement or working
 * document issued without a VAT rate, encoded as `I1:0` with no I2–I8.
 */
final class QrPayloadInput
{
    /**
     * @param list<QrRegionalTaxAmounts> $regionalTaxAmounts up to 3 entries; empty when $taxNotIndicated is true
     */
    public function __construct(
        private readonly string $issuerNif,
        private readonly string $customerNif,
        private readonly string $customerCountry,
        private readonly string $documentTypeCode,
        private readonly string $documentStatus,
        private readonly \DateTimeImmutable $documentDate,
        private readonly string $documentNumber,
        private readonly string $atcud,
        private readonly array $regionalTaxAmounts,
        private readonly bool $taxNotIndicated,
        private readonly ?Money $notSubjectToVat,
        private readonly ?Money $stampDuty,
        private readonly Money $totalTaxes,
        private readonly Money $grossTotal,
        private readonly ?Money $withholding,
        private readonly string $hashFourChars,
        private readonly string $certificateNumber,
        private readonly ?string $otherInformation,
    ) {
    }

    public function issuerNif(): string
    {
        return $this->issuerNif;
    }

    public function customerNif(): string
    {
        return $this->customerNif;
    }

    public function customerCountry(): string
    {
        return $this->customerCountry;
    }

    public function documentTypeCode(): string
    {
        return $this->documentTypeCode;
    }

    public function documentStatus(): string
    {
        return $this->documentStatus;
    }

    public function documentDate(): \DateTimeImmutable
    {
        return $this->documentDate;
    }

    public function documentNumber(): string
    {
        return $this->documentNumber;
    }

    public function atcud(): string
    {
        return $this->atcud;
    }

    /**
     * @return list<QrRegionalTaxAmounts>
     */
    public function regionalTaxAmounts(): array
    {
        return $this->regionalTaxAmounts;
    }

    public function taxNotIndicated(): bool
    {
        return $this->taxNotIndicated;
    }

    public function notSubjectToVat(): ?Money
    {
        return $this->notSubjectToVat;
    }

    public function stampDuty(): ?Money
    {
        return $this->stampDuty;
    }

    public function totalTaxes(): Money
    {
        return $this->totalTaxes;
    }

    public function grossTotal(): Money
    {
        return $this->grossTotal;
    }

    public function withholding(): ?Money
    {
        return $this->withholding;
    }

    public function hashFourChars(): string
    {
        return $this->hashFourChars;
    }

    public function certificateNumber(): string
    {
        return $this->certificateNumber;
    }

    public function otherInformation(): ?string
    {
        return $this->otherInformation;
    }
}
