<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidExemptionReasonCode extends \DomainException implements ProblemDetails
{
    public function __construct(string $exemptionReasonCode)
    {
        parent::__construct(\sprintf('"%s" is not a known exemption reason code.', $exemptionReasonCode));
    }

    public function problemType(): string
    {
        return 'invalid-exemption-reason-code';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
