<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class DocumentNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such document.');
    }

    public function problemType(): string
    {
        return 'document-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
