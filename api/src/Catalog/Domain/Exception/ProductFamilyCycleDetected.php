<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class ProductFamilyCycleDetected extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This would make the product family tree cyclic.');
    }

    public function problemType(): string
    {
        return 'product-family-cycle-detected';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
