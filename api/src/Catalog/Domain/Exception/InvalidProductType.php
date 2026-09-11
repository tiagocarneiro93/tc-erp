<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidProductType extends \DomainException implements ProblemDetails
{
    public function __construct(string $type)
    {
        parent::__construct(\sprintf('"%s" is not a valid SAF-T product type (P|S|O|E|I).', $type));
    }

    public function problemType(): string
    {
        return 'invalid-product-type';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
