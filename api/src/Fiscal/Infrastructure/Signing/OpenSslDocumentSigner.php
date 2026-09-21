<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Signing;

use App\Fiscal\Domain\Signing\DocumentSigner;
use App\Fiscal\Domain\Signing\SignedHash;

/**
 * Despacho 8632/2014 §6.2: RSA, 1024-bit private key, SHA-1 message
 * digest, PKCS#1 v1.5 padding, X.509 format, Base-64 encoding, Little
 * Endian — a legally mandated combination, not our choice. Despite being
 * weak by any modern (2026) cryptographic standard, it must be
 * implemented exactly as specified for AT certification; PHP's
 * `openssl_sign()` with `OPENSSL_ALGO_SHA1` and its default padding
 * (`OPENSSL_PKCS1_PADDING`) match it exactly.
 *
 * The dev private key lives at `var/signing/document-signing-dev.pem`
 * (inside `api/.gitignore`'s blanket `/var/` rule — CLAUDE.md: "the
 * signing private key is never committed"), generated locally via `make
 * signing-key`. Production key handling is the owner's responsibility
 * (CLAUDE.md) — out of this class's concern beyond reading whatever PEM
 * file `DOCUMENT_SIGNING_KEY_PATH` names.
 */
final class OpenSslDocumentSigner implements DocumentSigner
{
    public function __construct(
        private readonly string $privateKeyPath,
        private readonly int $keyVersion,
    ) {
    }

    public function sign(string $message): SignedHash
    {
        $pem = @file_get_contents($this->privateKeyPath);

        if (false === $pem) {
            throw new \RuntimeException(\sprintf('Cannot read the document-signing private key at "%s" — run `make signing-key` first.', $this->privateKeyPath));
        }

        $privateKey = openssl_pkey_get_private($pem);

        if (false === $privateKey) {
            throw new \RuntimeException(\sprintf('"%s" is not a valid RSA private key: %s', $this->privateKeyPath, openssl_error_string() ?: 'unknown error'));
        }

        $signature = '';
        if (!openssl_sign($message, $signature, $privateKey, \OPENSSL_ALGO_SHA1) || !\is_string($signature)) {
            throw new \RuntimeException('openssl_sign() failed: '.(openssl_error_string() ?: 'unknown error'));
        }

        return new SignedHash(base64_encode($signature), $this->keyVersion);
    }
}
