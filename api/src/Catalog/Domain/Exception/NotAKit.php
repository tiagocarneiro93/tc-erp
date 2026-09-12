<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class NotAKit extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This product is not a kit (kind must be "kit" to have components).');
    }

    public function problemType(): string
    {
        return 'not-a-kit';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
