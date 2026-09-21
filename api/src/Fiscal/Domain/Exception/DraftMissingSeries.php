<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * technical-scope.md §7.1 step 4 needs a series to lock — task 2.4's own
 * validation (`ValidateDraftHandler`) only checks a *set* `series_id`
 * resolves and matches the draft's document type, since a draft is
 * allowed to have none while still being edited. Issuance is the point
 * where one becomes mandatory.
 */
final class DraftMissingSeries extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This draft has no series selected; a series is required to issue a document.');
    }

    public function problemType(): string
    {
        return 'draft-missing-series';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
