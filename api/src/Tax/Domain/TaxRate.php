<?php

declare(strict_types=1);

namespace App\Tax\Domain;

use App\Shared\Domain\Decimal\Percentage;

/**
 * Global reference data (technical-scope.md §6.5), versioned by validity
 * dates; changes ship as a new data migration, never an edit to a
 * committed one — no admin UI in this phase (docs/decisions/0003).
 */
final class TaxRate
{
    public function __construct(
        private readonly TaxRateId $id,
        private readonly string $region,
        private readonly string $code,
        private readonly Percentage $percentage,
        private readonly \DateTimeImmutable $validFrom,
        private readonly ?\DateTimeImmutable $validTo,
        private readonly string $description,
    ) {
    }

    public function id(): TaxRateId
    {
        return $this->id;
    }

    /**
     * PT|PT-AC|PT-MA.
     */
    public function region(): string
    {
        return $this->region;
    }

    /**
     * SAF-T TaxCode: NOR|INT|RED|ISE|OUT.
     */
    public function code(): string
    {
        return $this->code;
    }

    public function percentage(): Percentage
    {
        return $this->percentage;
    }

    public function validFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function isValidAt(\DateTimeImmutable $date): bool
    {
        if ($date < $this->validFrom) {
            return false;
        }

        return null === $this->validTo || $date <= $this->validTo;
    }
}
