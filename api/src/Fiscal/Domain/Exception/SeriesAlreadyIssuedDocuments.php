<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * docs/plans/phase-3.md task 3.1, `at-ws-series-aspetos-especificos.pdf`
 * §1.3.3: `anularSerie` is only legal for a series that has never issued a
 * document ("declaracaoNaoEmissao" — the caller confirms this explicitly
 * to AT). A series that has already issued at least one document has no
 * AT operation that "un-registers" it — {@see \App\Fiscal\Domain\Series::finish()}
 * (`finalizarSerie`) is the correct way to stop using it.
 */
final class SeriesAlreadyIssuedDocuments extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('Cannot cancel a series that has already issued documents — finish it instead (at-ws-series-aspetos-especificos.pdf §1.3.3).');
    }

    public function problemType(): string
    {
        return 'series-already-issued-documents';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
