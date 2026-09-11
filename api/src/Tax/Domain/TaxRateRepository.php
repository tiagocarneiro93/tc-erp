<?php

declare(strict_types=1);

namespace App\Tax\Domain;

interface TaxRateRepository
{
    /**
     * @return list<TaxRate>
     */
    public function findAll(?string $region, ?\DateTimeImmutable $asOf): array;

    public function findApplicable(string $region, string $code, \DateTimeImmutable $date): ?TaxRate;
}
