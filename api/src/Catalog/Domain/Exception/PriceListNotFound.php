<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class PriceListNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such price list.');
    }

    public function problemType(): string
    {
        return 'price-list-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
