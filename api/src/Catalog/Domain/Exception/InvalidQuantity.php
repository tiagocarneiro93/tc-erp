<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidQuantity extends \DomainException implements ProblemDetails
{
    public function __construct(string $quantity)
    {
        parent::__construct(\sprintf('"%s" is not a valid quantity (at most 6 decimal places).', $quantity));
    }

    public function problemType(): string
    {
        return 'invalid-quantity';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
