<?php

declare(strict_types=1);

namespace App\Parties\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class SupplierNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such supplier.');
    }

    public function problemType(): string
    {
        return 'supplier-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
