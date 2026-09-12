<?php

declare(strict_types=1);

namespace App\Parties\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidPaymentTermsId extends \DomainException implements ProblemDetails
{
    public function __construct(string $paymentTermsId)
    {
        parent::__construct(\sprintf('"%s" is not a known payment terms id.', $paymentTermsId));
    }

    public function problemType(): string
    {
        return 'invalid-payment-terms-id';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
