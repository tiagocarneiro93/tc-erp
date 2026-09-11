<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class NotAMember extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This user is not a member of this company.');
    }

    public function problemType(): string
    {
        return 'not-a-member';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
