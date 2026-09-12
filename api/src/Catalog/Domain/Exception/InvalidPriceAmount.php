<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidPriceAmount extends \DomainException implements ProblemDetails
{
    public function __construct(string $amount)
    {
        parent::__construct(\sprintf('"%s" is not a valid decimal amount.', $amount));
    }

    public function problemType(): string
    {
        return 'invalid-price-amount';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
