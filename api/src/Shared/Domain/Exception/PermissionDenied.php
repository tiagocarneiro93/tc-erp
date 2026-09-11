<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Cross-module twin of `Platform\Domain\Exception\PermissionDenied`
 * (docs/decisions/0004) — thrown by modules other than Platform, since
 * Deptrac forbids depending on another module's Domain exceptions.
 */
final class PermissionDenied extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('You do not have permission to do this.');
    }

    public function problemType(): string
    {
        return 'permission-denied';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
