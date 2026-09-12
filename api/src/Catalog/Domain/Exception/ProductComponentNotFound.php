<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class ProductComponentNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This kit has no such component.');
    }

    public function problemType(): string
    {
        return 'product-component-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
