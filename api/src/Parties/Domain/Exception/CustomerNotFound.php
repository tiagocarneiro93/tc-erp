<?php

declare(strict_types=1);

namespace App\Parties\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class CustomerNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such customer.');
    }

    public function problemType(): string
    {
        return 'customer-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
