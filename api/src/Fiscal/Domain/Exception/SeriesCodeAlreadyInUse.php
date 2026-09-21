<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * technical-scope.md §6.6: `UNIQUE (company_id, document_type, code)`.
 */
final class SeriesCodeAlreadyInUse extends \DomainException implements ProblemDetails
{
    public function __construct(string $documentType, string $code)
    {
        parent::__construct(\sprintf('A series with document type "%s" and code "%s" already exists.', $documentType, $code));
    }

    public function problemType(): string
    {
        return 'series-code-already-in-use';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
