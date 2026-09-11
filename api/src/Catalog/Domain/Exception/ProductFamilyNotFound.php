<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class ProductFamilyNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such product family.');
    }

    public function problemType(): string
    {
        return 'product-family-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
