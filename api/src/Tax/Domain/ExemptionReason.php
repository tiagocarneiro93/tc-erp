<?php

declare(strict_types=1);

namespace App\Tax\Domain;

/**
 * Global reference data (technical-scope.md §6.5) — the official AT table
 * of VAT exemption/non-liquidation reason codes, per
 * docs/legal/at-tabela-codigos-motivo-isencao.pdf (V4.0, 18 Jun 2026).
 */
final class ExemptionReason
{
    public function __construct(
        private readonly string $code,
        private readonly string $description,
        private readonly string $legalReference,
        private readonly \DateTimeImmutable $validFrom,
        private readonly ?\DateTimeImmutable $validTo,
    ) {
    }

    /**
     * M01..M99.
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * "Menção que consta da fatura" — the exact wording the invoice must show.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * "Norma aplicável" — the legal basis, verbatim from the source table.
     */
    public function legalReference(): string
    {
        return $this->legalReference;
    }

    public function validFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function isValidAt(\DateTimeImmutable $date): bool
    {
        if ($date < $this->validFrom) {
            return false;
        }

        return null === $this->validTo || $date <= $this->validTo;
    }
}
