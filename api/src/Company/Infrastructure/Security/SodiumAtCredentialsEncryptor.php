<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\AtCredentialsEncryptor;
use App\Company\Domain\Exception\AtCredentialsDecryptionFailed;

/**
 * technical-scope.md §8.2: libsodium secretbox (XSalsa20-Poly1305),
 * authenticated symmetric encryption. Ciphertext is stored as
 * base64(nonce || box) so a single TEXT column carries everything needed to
 * decrypt; the key is a dev-only committed default in .env
 * (AT_CREDENTIALS_ENCRYPTION_KEY), overridden by a real secret in
 * production (CLAUDE.md — secrets are never committed for real
 * environments), same treatment as the Phase 2 signing key
 * (docs/plans/phase-1.md task 1.4).
 */
final class SodiumAtCredentialsEncryptor implements AtCredentialsEncryptor
{
    private string $key;

    public function __construct(string $base64Key)
    {
        $key = base64_decode($base64Key, true);

        if (false === $key || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($key)) {
            throw new \InvalidArgumentException(\sprintf('AT_CREDENTIALS_ENCRYPTION_KEY must be %d base64-encoded random bytes.', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        }

        $this->key = $key;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce.$box);
    }

    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, true);

        if (false === $decoded || \strlen($decoded) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new AtCredentialsDecryptionFailed();
        }

        $nonce = substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box = substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($box, $nonce, $this->key);

        if (false === $plaintext) {
            throw new AtCredentialsDecryptionFailed();
        }

        return $plaintext;
    }
}
