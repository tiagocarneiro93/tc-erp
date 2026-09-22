<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Infrastructure\Security;

use App\Company\Domain\AtCredentials;
use App\Company\Domain\AtCredentialsEncryptor;
use App\Company\Domain\AtCredentialsRepository;
use App\Company\Infrastructure\Security\CompanyAtCredentialsProvider;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;

/**
 * docs/plans/phase-3.md task 3.1e: the cross-module implementation of
 * {@see \App\Shared\Domain\Company\AtCredentialsProvider} — proves it
 * decrypts through the same port `AtCredentialsController` (task 1.4)
 * uses, rather than a second decryption path.
 */
final class CompanyAtCredentialsProviderTest extends TestCase
{
    public function testReturnsNullWhenNoCredentialsAreConfigured(): void
    {
        $companyId = CompanyId::generate();
        $repository = $this->createMock(AtCredentialsRepository::class);
        $repository->method('find')->with($companyId)->willReturn(null);
        $encryptor = $this->createMock(AtCredentialsEncryptor::class);

        $provider = new CompanyAtCredentialsProvider($repository, $encryptor);

        self::assertNull($provider->forCompany($companyId));
    }

    public function testReturnsTheDecryptedSubuserAndPassword(): void
    {
        $companyId = CompanyId::generate();
        $credentials = AtCredentials::create($companyId, '555555555/1', 'ciphertext');

        $repository = $this->createMock(AtCredentialsRepository::class);
        $repository->method('find')->with($companyId)->willReturn($credentials);
        $encryptor = $this->createMock(AtCredentialsEncryptor::class);
        $encryptor->method('decrypt')->with('ciphertext')->willReturn('plaintext-password');

        $provider = new CompanyAtCredentialsProvider($repository, $encryptor);
        $decrypted = $provider->forCompany($companyId);

        self::assertNotNull($decrypted);
        self::assertSame('555555555/1', $decrypted->subuser);
        self::assertSame('plaintext-password', $decrypted->password);
    }
}
