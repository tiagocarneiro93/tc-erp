<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\AtCredentialsEncryptor;
use App\Company\Domain\AtCredentialsRepository;
use App\Shared\Domain\Company\AtCredentialsProvider;
use App\Shared\Domain\Company\DecryptedAtCredentials;
use App\Shared\Domain\CompanyId;

/**
 * Implements the {@see AtCredentialsProvider} cross-module port by wrapping
 * the same repository/encryptor the Settings screen's own
 * `UpdateAtCredentialsHandler`/`TestAtCredentialsHandler` (task 1.4) use —
 * one source of truth for how these credentials are stored and decrypted.
 */
final class CompanyAtCredentialsProvider implements AtCredentialsProvider
{
    public function __construct(
        private readonly AtCredentialsRepository $credentials,
        private readonly AtCredentialsEncryptor $encryptor,
    ) {
    }

    public function forCompany(CompanyId $companyId): ?DecryptedAtCredentials
    {
        $credentials = $this->credentials->find($companyId);

        if (null === $credentials) {
            return null;
        }

        return new DecryptedAtCredentials(
            $credentials->subuser(),
            $this->encryptor->decrypt($credentials->passwordEncrypted()),
        );
    }
}
