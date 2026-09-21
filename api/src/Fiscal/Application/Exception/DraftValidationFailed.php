<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Exception;

use App\Fiscal\Application\Query\DraftValidationError;
use App\Shared\Domain\Exception\ProblemDetails;

/**
 * §7.1 step 2: issuance re-runs `ValidateDraftHandler`'s rules (task 2.4)
 * rather than trusting the caller already called `GET .../drafts/{id}/validate`
 * first. `ProblemDetailsExceptionListener` only renders a single `title`
 * for a {@see ProblemDetails} exception (unlike the structured `errors`
 * array that endpoint returns), so every failing field is joined into one
 * message here — a caller wanting the structured, per-field list still has
 * that endpoint for it.
 */
final class DraftValidationFailed extends \DomainException implements ProblemDetails
{
    /**
     * @param list<DraftValidationError> $errors
     */
    public function __construct(array $errors)
    {
        parent::__construct('Cannot issue: '.implode('; ', array_map(
            static fn (DraftValidationError $e): string => \sprintf('%s: %s', $e->field, $e->message),
            $errors,
        )));
    }

    public function problemType(): string
    {
        return 'draft-validation-failed';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
