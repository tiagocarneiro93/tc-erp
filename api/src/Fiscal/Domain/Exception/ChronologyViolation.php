<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * technical-scope.md §7.1 step 5: `issue_date`/`system_entry_at` must never
 * move backwards relative to the series' own last values (§6.9's DB-level
 * invariant, enforced here first so the caller gets a clear 422 instead of
 * a trigger-raised database error).
 */
final class ChronologyViolation extends \DomainException implements ProblemDetails
{
    public function __construct(string $field, \DateTimeImmutable $attempted, \DateTimeImmutable $mustNotPrecede)
    {
        parent::__construct(\sprintf(
            '%s ("%s") cannot precede this series\' last %s ("%s").',
            $field,
            $attempted->format('c'),
            $field,
            $mustNotPrecede->format('c'),
        ));
    }

    public function problemType(): string
    {
        return 'chronology-violation';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
