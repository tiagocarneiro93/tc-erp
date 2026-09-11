<?php

declare(strict_types=1);

namespace App\Tax\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * Thrown by {@see \App\Tax\Domain\TaxRateResolver} instead of silently
 * picking the nearest rate (docs/plans/phase-1.md task 1.2) — a missing
 * rate means the seed data has a gap, not something the caller can fix.
 */
final class NoApplicableTaxRate extends \DomainException implements ProblemDetails
{
    public function __construct(string $region, string $code, \DateTimeImmutable $date)
    {
        parent::__construct(\sprintf(
            'No tax rate found for region "%s", code "%s" at %s.',
            $region,
            $code,
            $date->format('Y-m-d'),
        ));
    }

    public function problemType(): string
    {
        return 'no-applicable-tax-rate';
    }

    public function httpStatus(): int
    {
        return 500;
    }
}
