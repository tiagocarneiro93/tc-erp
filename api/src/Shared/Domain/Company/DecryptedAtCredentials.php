<?php

declare(strict_types=1);

namespace App\Shared\Domain\Company;

/**
 * The plaintext form of `Company\Domain\AtCredentials`, for the one moment
 * it's needed: building a SOAP request. Nothing outside {@see AtCredentialsProvider}'s
 * implementation ever holds the ciphertext or the decryption key — this
 * carries only what a caller (`AtIntegration`, docs/plans/phase-3.md task
 * 3.1e) actually needs.
 */
final class DecryptedAtCredentials
{
    public function __construct(
        public readonly string $subuser,
        public readonly string $password,
    ) {
    }
}
