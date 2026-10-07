<?php

declare(strict_types=1);

namespace App\Output\Domain;

use App\Shared\Domain\Exception\ProblemDetails;

final class StoredFileNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such stored file.');
    }

    public function problemType(): string
    {
        return 'stored-file-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
