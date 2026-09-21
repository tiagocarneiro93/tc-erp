<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class UnknownDocumentType extends \DomainException implements ProblemDetails
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf('"%s" is not a known document type.', $code));
    }

    public function problemType(): string
    {
        return 'unknown-document-type';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
