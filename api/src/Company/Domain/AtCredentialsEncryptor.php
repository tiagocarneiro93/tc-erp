<?php

declare(strict_types=1);

namespace App\Company\Domain;

use App\Company\Domain\Exception\AtCredentialsDecryptionFailed;

/**
 * technical-scope.md §8.2: "AT credentials per company: encrypted at rest
 * (libsodium, key from secrets manager)". Symmetric, not sealed-box: the
 * application itself must be able to decrypt again in Phase 3 to
 * authenticate webservice calls, not just receive a one-way encrypted value.
 */
interface AtCredentialsEncryptor
{
    public function encrypt(string $plaintext): string;

    /**
     * @throws AtCredentialsDecryptionFailed if the ciphertext is corrupt or
     *                                       was not produced with the current key
     */
    public function decrypt(string $ciphertext): string;
}
