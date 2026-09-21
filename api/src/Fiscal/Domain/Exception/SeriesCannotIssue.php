<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Fiscal\Domain\SeriesStatus;
use App\Shared\Domain\Exception\ProblemDetails;

/**
 * technical-scope.md §7.6: "a series cannot issue documents without a
 * validation code" — also true, by the same rule, for any series that
 * isn't `active` at all. Thrown by the issuance use case's own
 * re-check under the series row lock (docs/plans/phase-2.md task 2.6
 * step 4), since a series can legitimately be finished/cancelled by a
 * concurrent request between draft validation and the lock being granted.
 */
final class SeriesCannotIssue extends \DomainException implements ProblemDetails
{
    public function __construct(SeriesStatus $status)
    {
        parent::__construct(\sprintf('This series cannot issue documents: status is "%s", or it has no AT validation code.', $status->value));
    }

    public function problemType(): string
    {
        return 'series-cannot-issue';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
