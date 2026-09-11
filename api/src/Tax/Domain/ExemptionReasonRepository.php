<?php

declare(strict_types=1);

namespace App\Tax\Domain;

interface ExemptionReasonRepository
{
    /**
     * @return list<ExemptionReason>
     */
    public function findAll(?\DateTimeImmutable $asOf): array;

    public function find(string $code): ?ExemptionReason;
}
