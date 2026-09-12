<?php

declare(strict_types=1);

namespace App\Parties\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * technical-scope.md §6.3: the generic "Consumidor final" customer (NIF
 * 999999990) is platform-seeded, singular, and referenced by its fixed NIF
 * — it must always exist and always mean the same thing, so it can never be
 * edited or deactivated by a company user.
 */
final class FinalConsumerCustomerIsProtected extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('The "Consumidor final" customer cannot be edited or deactivated.');
    }

    public function problemType(): string
    {
        return 'final-consumer-customer-is-protected';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
