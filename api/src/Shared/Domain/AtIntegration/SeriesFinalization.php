<?php

declare(strict_types=1);

namespace App\Shared\Domain\AtIntegration;

/**
 * `finalizarSerie`'s request fields. `$lastIssuedNumber` must be positive —
 * {@see \App\Fiscal\Domain\Series::finish()} already guarantees that before
 * this is ever built (`at-ws-series-aspetos-especificos.pdf` §2.3.2).
 */
final class SeriesFinalization
{
    public function __construct(
        public readonly string $code,
        public readonly string $documentType,
        public readonly string $validationCode,
        public readonly int $lastIssuedNumber,
    ) {
    }
}
