<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * docs/plans/phase-3.md task 3.1, `at-ws-series-aspetos-especificos.pdf`
 * §2.3.2: `finalizarSerie`'s `seqUltimoDocEmitido` field requires a
 * positive number — there is no AT operation to "finish" a series that
 * never issued anything. {@see \App\Fiscal\Domain\Series::cancel()}
 * (`anularSerie`) is the correct way to stop using an unused one instead.
 */
final class SeriesHasNoIssuedDocuments extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('Cannot finish a series that has never issued a document — cancel it instead (at-ws-series-aspetos-especificos.pdf §2.3.2).');
    }

    public function problemType(): string
    {
        return 'series-has-no-issued-documents';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
