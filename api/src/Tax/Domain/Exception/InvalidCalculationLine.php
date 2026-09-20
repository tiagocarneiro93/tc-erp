<?php

declare(strict_types=1);

namespace App\Tax\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * A `/calculate` request line failed to parse into something
 * {@see \App\Tax\Domain\PriceCalculator} can compute — malformed decimal
 * strings, an unknown discount type, or a missing required field. Always
 * the caller's fault (422), never the "seed data gap" case
 * {@see NoApplicableTaxRate} covers.
 */
final class InvalidCalculationLine extends \DomainException implements ProblemDetails
{
    public function __construct(?int $lineIndex, string $reason)
    {
        parent::__construct(null === $lineIndex
            ? $reason
            : \sprintf('Line %d is invalid: %s', $lineIndex, $reason));
    }

    public function problemType(): string
    {
        return 'invalid-calculation-line';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
