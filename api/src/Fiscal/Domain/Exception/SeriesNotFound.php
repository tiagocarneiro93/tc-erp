<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class SeriesNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such series.');
    }

    public function problemType(): string
    {
        return 'series-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
