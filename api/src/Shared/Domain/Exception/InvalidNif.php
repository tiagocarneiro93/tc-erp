<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Cross-module twin of `Platform\Domain\Exception\InvalidNif`
 * (docs/decisions/0004) — thrown by modules other than Platform when
 * `Nif::fromString()` rejects a value, since Deptrac forbids depending on
 * another module's Domain exceptions.
 */
final class InvalidNif extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This is not a valid NIF.');
    }

    public function problemType(): string
    {
        return 'nif-invalid';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
