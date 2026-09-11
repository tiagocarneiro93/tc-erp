<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class ProductNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such product.');
    }

    public function problemType(): string
    {
        return 'product-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
