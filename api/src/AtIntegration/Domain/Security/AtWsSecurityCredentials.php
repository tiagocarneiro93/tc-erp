<?php

declare(strict_types=1);

namespace App\AtIntegration\Domain\Security;

/**
 * The three base64 fields the WS-Security `SoapHeader` needs
 * (`at-ws-series-aspetos-genericos.pdf` §4.1 / the byte-identical e-Fatura
 * requirement): `Password`/`Created` encrypted with a per-request AES key,
 * `Nonce` that key itself, RSA-wrapped for AT's public key. `Username`
 * (`<NIF>/<UserId>`) isn't part of this — it's sent in the clear, so the
 * SOAP header builder (task 3.1f) adds it separately.
 */
final class AtWsSecurityCredentials
{
    public function __construct(
        public readonly string $passwordBase64,
        public readonly string $nonceBase64,
        public readonly string $createdBase64,
    ) {
    }
}
