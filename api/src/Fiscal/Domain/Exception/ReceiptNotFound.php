<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class ReceiptNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such receipt.');
    }

    public function problemType(): string
    {
        return 'receipt-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
