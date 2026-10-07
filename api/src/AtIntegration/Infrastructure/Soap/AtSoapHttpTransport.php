<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Soap;

/**
 * The one network hop of {@see EFaturaWSClient}: POST a complete SOAP 1.1
 * envelope, get the raw response body back. Split out so the client's request
 * and response handling is testable without AT, TLS certificates or
 * `ext-soap` — and so that hop's failure modes (unreachable, timeout) have
 * exactly one place to be reported from.
 */
interface AtSoapHttpTransport
{
    /**
     * @throws AtTransportFailed when no HTTP response body could be obtained at all
     */
    public function post(string $url, string $envelope): string;
}
