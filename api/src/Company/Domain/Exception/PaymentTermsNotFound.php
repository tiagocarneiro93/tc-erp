<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class PaymentTermsNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such payment terms.');
    }

    public function problemType(): string
    {
        return 'payment-terms-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
