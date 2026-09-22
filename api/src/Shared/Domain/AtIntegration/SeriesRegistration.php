<?php

declare(strict_types=1);

namespace App\Shared\Domain\AtIntegration;

/**
 * `registarSerie`'s request fields this app actually has to supply — the
 * rest (`numCertSWFatur`, `meioProcessamento`) are constants the adapter
 * fills in itself (docs/plans/phase-3.md decision 6/§1.3.9), not caller
 * concerns. `$documentType` is AT's own `tipoDoc` code
 * (`at-ws-series-aspetos-especificos.pdf` §1.3.8) — the same value already
 * used everywhere in this app as `document_type` (FT, OR, RG, …); the
 * adapter derives `classeDoc` from it.
 */
final class SeriesRegistration
{
    public function __construct(
        public readonly string $code,
        public readonly bool $isTraining,
        public readonly string $documentType,
        public readonly int $startNumber,
        public readonly \DateTimeImmutable $expectedStartDate,
    ) {
    }
}
