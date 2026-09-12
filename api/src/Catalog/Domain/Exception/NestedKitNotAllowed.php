<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * technical-scope.md §7.10.1: "Nested kits: not in v1" — disallowed
 * outright, not just cycle-checked (a kit containing itself is also
 * rejected by this same check, since the kit's own `kind` is `kit`).
 */
final class NestedKitNotAllowed extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('A kit\'s component cannot itself be a kit (nested kits are not supported).');
    }

    public function problemType(): string
    {
        return 'nested-kit-not-allowed';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
