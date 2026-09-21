<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * Despacho 8632/2014 §3.3.3–3.3.5: once a product has been referenced by at
 * least one issued document, its `description` locks.
 */
final class ProductDescriptionIsLocked extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This product has been referenced by an issued document: the description cannot be changed.');
    }

    public function problemType(): string
    {
        return 'product-description-is-locked';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
