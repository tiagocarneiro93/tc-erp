<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidAtSubuserFormat extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('The AT subuser must be in the form "<NIF>/<sequence number>".');
    }

    public function problemType(): string
    {
        return 'invalid-at-subuser-format';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
