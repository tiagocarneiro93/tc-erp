<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * A line with no `product_id` (free text, no catalog product) must supply
 * its own `product_code`/`description`/`product_type`/`unit_code` directly
 * — {@see \App\Shared\Domain\Fiscal\ProductSnapshotProvider} has nothing to
 * resolve them from. `ValidateDraftHandler` (task 2.4) doesn't check this
 * shape (it only requires calculable pricing fields), so issuance is where
 * a malformed free-text line is first caught.
 */
final class InvalidDraftLineForIssuance extends \DomainException implements ProblemDetails
{
    public function __construct(int $lineIndex, string $field)
    {
        parent::__construct(\sprintf('Line %d is missing "%s" (required for a line with no product_id).', $lineIndex, $field));
    }

    public function problemType(): string
    {
        return 'invalid-draft-line-for-issuance';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
