<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * `ValidateDraftHandler` already requires a non-null `calculated` before
 * issuance is attempted, but issuance recomputes canonically at signing
 * time rather than trusting that cached value (technical-scope.md §7.9:
 * "never trust client totals") — so a tax rate or similar reference data
 * removed in the moment between validation and issuance is still caught
 * here, as a 422 rather than a confusing 500.
 */
final class DraftNotCalculableAtIssuance extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This draft could not be calculated at issuance time; check the line data.');
    }

    public function problemType(): string
    {
        return 'draft-not-calculable-at-issuance';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
