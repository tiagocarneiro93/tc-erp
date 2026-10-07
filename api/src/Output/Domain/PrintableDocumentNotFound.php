<?php

declare(strict_types=1);

namespace App\Output\Domain;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * No *issued* document with this id — which includes every draft: a draft has
 * no `documents` row, so asking for its PDF is simply asking for something that
 * does not exist (CLAUDE.md: drafts are never printable or sendable).
 */
final class PrintableDocumentNotFound extends \DomainException implements ProblemDetails
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
