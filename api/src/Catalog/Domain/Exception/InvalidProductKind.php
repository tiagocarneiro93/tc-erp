<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidProductKind extends \DomainException implements ProblemDetails
{
    public function __construct(string $kind)
    {
        parent::__construct(\sprintf('"%s" is not a valid product kind (simple|kit).', $kind));
    }

    public function problemType(): string
    {
        return 'invalid-product-kind';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
