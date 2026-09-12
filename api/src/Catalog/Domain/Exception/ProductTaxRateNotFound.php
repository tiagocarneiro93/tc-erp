<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * Never expected in normal operation — a product's `tax_rate_id` is
 * validated to exist when the product is created/updated. A 500, not a
 * client error: it would mean that invariant was violated some other way.
 */
final class ProductTaxRateNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This product\'s tax rate no longer exists.');
    }

    public function problemType(): string
    {
        return 'product-tax-rate-not-found';
    }

    public function httpStatus(): int
    {
        return 500;
    }
}
