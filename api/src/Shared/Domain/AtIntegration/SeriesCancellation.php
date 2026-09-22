<?php

declare(strict_types=1);

namespace App\Shared\Domain\AtIntegration;

/**
 * `anularSerie`'s request fields. `motivo` isn't a caller concern: AT's own
 * table (`at-ws-series-aspetos-especificos.pdf` §1.3.10) names exactly one
 * value ("ER" — Anulação por erro de registo), so the adapter sends it
 * directly. `declaracaoNaoEmissao` is always `true`: this call is only ever
 * reached once {@see \App\Fiscal\Domain\Series::cancel()} has already
 * confirmed the series never issued a document (§1.3.3), so the
 * confirmation is true by construction, not a caller's claim to trust.
 */
final class SeriesCancellation
{
    public function __construct(
        public readonly string $code,
        public readonly string $documentType,
        public readonly string $validationCode,
    ) {
    }
}
