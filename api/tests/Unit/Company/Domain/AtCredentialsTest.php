<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Domain;

use App\Company\Domain\AtCredentials;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AtCredentialsTest extends TestCase
{
    /**
     * docs/legal/at-ws-efatura-aspetos-genericos.pdf §2's own example:
     * "obtém a identificação do subutilizador (e.g., 555555555/55)".
     */
    public function testAcceptsTheDocumentedNifSlashSequenceFormat(): void
    {
        self::assertTrue(AtCredentials::isValidSubuserFormat('555555555/55'));
        self::assertTrue(AtCredentials::isValidSubuserFormat('123456789/1'));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidFormats(): iterable
    {
        yield 'missing suffix' => ['555555555'];
        yield 'missing slash' => ['55555555555'];
        yield 'nif too short' => ['12345/1'];
        yield 'non-numeric suffix' => ['555555555/ab'];
        yield 'empty string' => [''];
    }

    #[DataProvider('invalidFormats')]
    public function testRejectsAnythingElse(string $subuser): void
    {
        self::assertFalse(AtCredentials::isValidSubuserFormat($subuser));
    }

    public function testUpdatingCredentialsClearsAnyPreviousValidationResult(): void
    {
        $credentials = AtCredentials::create(CompanyId::generate(), '555555555/55', 'ciphertext-a');
        $credentials->recordValidationSuccess(new \DateTimeImmutable('2026-01-01'));

        $credentials->updateCredentials('555555555/56', 'ciphertext-b');

        self::assertSame('555555555/56', $credentials->subuser());
        self::assertSame('ciphertext-b', $credentials->passwordEncrypted());
        self::assertNull($credentials->validatedAt());
        self::assertNull($credentials->lastError());
    }
}
