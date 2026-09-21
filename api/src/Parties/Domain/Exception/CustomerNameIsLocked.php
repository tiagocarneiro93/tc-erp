<?php

declare(strict_types=1);

namespace App\Parties\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * Despacho 8632/2014 §3.3.3–3.3.5: once a customer has at least one issued
 * document, its `name` locks — no exceptions (unlike `nif`, which may still
 * be filled in or corrected from the generic final-consumer value).
 */
final class CustomerNameIsLocked extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This customer has an issued document: the name cannot be changed.');
    }

    public function problemType(): string
    {
        return 'customer-name-is-locked';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
