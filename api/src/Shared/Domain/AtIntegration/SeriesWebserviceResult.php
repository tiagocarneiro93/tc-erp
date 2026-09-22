<?php

declare(strict_types=1);

namespace App\Shared\Domain\AtIntegration;

/**
 * The `operationResultInfo`/`seriesInfo` fields every series operation's
 * response carries (`at-ws-series-aspetos-especificos.pdf` §2.5) — enough
 * for the caller to decide what happened and to record an `at_communications`
 * audit row (§6.12), without leaking the raw SOAP response shape.
 * `$validationCode` is only ever set on a successful `register()` call.
 */
final class SeriesWebserviceResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly int $responseCode,
        public readonly string $responseMessage,
        public readonly ?string $validationCode = null,
    ) {
    }
}
