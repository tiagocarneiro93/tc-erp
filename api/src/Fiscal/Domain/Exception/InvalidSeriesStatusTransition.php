<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Fiscal\Domain\SeriesStatus;
use App\Shared\Domain\Exception\ProblemDetails;

/**
 * technical-scope.md §7.6: `draft` → `active` → `finished`/`cancelled`,
 * both terminal. Thrown by every {@see \App\Fiscal\Domain\Series} method
 * that changes status, for any status the transition doesn't allow from —
 * e.g. activating an already-active series, finishing a draft one, or
 * touching a finished/cancelled one at all.
 */
final class InvalidSeriesStatusTransition extends \DomainException implements ProblemDetails
{
    public function __construct(string $action, SeriesStatus $actualStatus)
    {
        parent::__construct(\sprintf('Cannot %s a series with status "%s".', $action, $actualStatus->value));
    }

    public function problemType(): string
    {
        return 'invalid-series-status-transition';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
