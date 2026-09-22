<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * docs/plans/phase-3.md task 3.1c, `at-ws-series-aspetos-especificos.pdf`
 * §1.3.2: a series identifier AT would reject before this app ever gets to
 * call `registarSerie` (Phase 3) — checked here too, so a series created
 * now doesn't sit invalid until that call is finally made.
 */
final class InvalidSeriesCode extends \DomainException implements ProblemDetails
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf('"%s" is not a valid series code (at-ws-series-aspetos-especificos.pdf §1.3.2: max 35 chars, letters/digits/"._-" only, no leading/trailing/doubled separator, cannot start with "AT" — reserved for AT\'s own programs).', $code));
    }

    public function problemType(): string
    {
        return 'invalid-series-code';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
