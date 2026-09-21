<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class DocumentDraftNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No such draft.');
    }

    public function problemType(): string
    {
        return 'document-draft-not-found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
