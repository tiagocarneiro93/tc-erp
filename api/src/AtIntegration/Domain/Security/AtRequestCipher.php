<?php

declare(strict_types=1);

namespace App\AtIntegration\Domain\Security;

/**
 * docs/plans/phase-3.md task 3.1d: the WS-Security password cipher every
 * AT webservice call needs, port + concrete adapter split the same way
 * {@see \App\Fiscal\Domain\Signing\DocumentSigner} splits from its OpenSSL
 * implementation — the mechanics belong to one seam, not scattered across
 * every SOAP client.
 */
interface AtRequestCipher
{
    public function buildCredentials(string $password, \DateTimeImmutable $now): AtWsSecurityCredentials;
}
