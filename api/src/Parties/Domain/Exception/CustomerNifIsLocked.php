<?php

declare(strict_types=1);

namespace App\Parties\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * Despacho 8632/2014 §3.3.3–3.3.5: once a customer has at least one issued
 * document, its `nif` locks — except filling a previously-blank value or
 * replacing the generic final-consumer NIF (999999990) with a real one.
 */
final class CustomerNifIsLocked extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This customer has an issued document: the NIF cannot be changed, except to fill in a previously blank value or replace the generic 999999990 with a real one.');
    }

    public function problemType(): string
    {
        return 'customer-nif-is-locked';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
