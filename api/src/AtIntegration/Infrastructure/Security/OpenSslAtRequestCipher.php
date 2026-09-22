<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Security;

use App\AtIntegration\Domain\Security\AtRequestCipher;
use App\AtIntegration\Domain\Security\AtWsSecurityCredentials;

/**
 * `at-ws-series-aspetos-genericos.pdf` §4.1 (byte-identical requirement in
 * `at-ws-efatura-aspetos-genericos.pdf`'s own SOAP header section):
 *
 * - `Ks`: a fresh random 128-bit AES key, one per request, never reused.
 * - `Nonce = Base64(RSA-encrypt(Ks, AT's public key))`.
 * - `Password`/`Created = Base64(AES-128-ECB-PKCS5(Ks, value))`.
 *
 * **One parameter neither manual actually states**: the RSA padding scheme
 * for wrapping `Ks`. Both manuals name the algorithm ("RSA") and the key
 * ("chave pública do Sistema de Autenticação") but never a padding mode.
 * This uses `OPENSSL_PKCS1_PADDING` — OpenSSL's own default for
 * `openssl_public_encrypt()` and the conventional choice for this exact
 * "wrap a symmetric key" pattern in webservices of this vintage — as a
 * flagged assumption, not a confirmed fact (CLAUDE.md: never guess an AT
 * technical format without saying so). Per docs/plans/phase-3.md decision
 * 10, this can only really be verified by a live call against AT's test
 * environment; if that call fails on decryption specifically, this is the
 * first thing to change.
 *
 * `openssl_encrypt()`'s default padding for `aes-128-ecb` is PKCS7, which
 * for a 16-byte AES block is byte-identical to what Java (and this
 * manual) calls "PKCS5Padding" — same algorithm, different historical
 * name, not a second assumption.
 */
final class OpenSslAtRequestCipher implements AtRequestCipher
{
    private const AES_KEY_BYTES = 16;

    public function __construct(
        private readonly string $publicKeyPath,
    ) {
    }

    public function buildCredentials(string $password, \DateTimeImmutable $now): AtWsSecurityCredentials
    {
        $key = random_bytes(self::AES_KEY_BYTES);

        return new AtWsSecurityCredentials(
            passwordBase64: $this->encryptWithKey($password, $key),
            nonceBase64: $this->wrapKey($key),
            createdBase64: $this->encryptWithKey($this->formatTimestamp($now), $key),
        );
    }

    private function wrapKey(string $key): string
    {
        $pem = @file_get_contents($this->publicKeyPath);

        if (false === $pem) {
            throw new \RuntimeException(\sprintf('Cannot read the AT public key at "%s" — check AT_PUBLIC_KEY_PATH.', $this->publicKeyPath));
        }

        $publicKey = openssl_pkey_get_public($pem);

        if (false === $publicKey) {
            throw new \RuntimeException(\sprintf('"%s" is not a valid public key: %s', $this->publicKeyPath, openssl_error_string() ?: 'unknown error'));
        }

        if (!openssl_public_encrypt($key, $encrypted, $publicKey, \OPENSSL_PKCS1_PADDING) || !\is_string($encrypted)) {
            throw new \RuntimeException('Failed to RSA-encrypt the AT request key.');
        }

        return base64_encode($encrypted);
    }

    private function encryptWithKey(string $plaintext, string $key): string
    {
        $encrypted = openssl_encrypt($plaintext, 'aes-128-ecb', $key, \OPENSSL_RAW_DATA);

        if (false === $encrypted) {
            throw new \RuntimeException('Failed to AES-encrypt an AT request field.');
        }

        return base64_encode($encrypted);
    }

    /**
     * ISO 8601, UTC, milliseconds (`at-ws-series-aspetos-genericos.pdf`
     * §4's own worked example shows two fractional digits —
     * "2013-01-01T19:20:30.45Z" — but ISO 8601 doesn't fix the fractional
     * digit count, so three (PHP's native millisecond precision) is a
     * conforming, not a deviating, choice.
     */
    private function formatTimestamp(\DateTimeImmutable $now): string
    {
        return $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
}
