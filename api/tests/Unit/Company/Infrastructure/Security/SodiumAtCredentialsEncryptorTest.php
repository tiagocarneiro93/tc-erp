<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Infrastructure\Security;

use App\Company\Domain\Exception\AtCredentialsDecryptionFailed;
use App\Company\Infrastructure\Security\SodiumAtCredentialsEncryptor;
use PHPUnit\Framework\TestCase;

final class SodiumAtCredentialsEncryptorTest extends TestCase
{
    public function testEncryptThenDecryptRoundTripsToTheOriginalPlaintext(): void
    {
        $encryptor = new SodiumAtCredentialsEncryptor(base64_encode(random_bytes(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));

        $ciphertext = $encryptor->encrypt('a-secret-password');

        self::assertNotSame('a-secret-password', $ciphertext);
        self::assertSame('a-secret-password', $encryptor->decrypt($ciphertext));
    }

    public function testTwoEncryptionsOfTheSamePlaintextProduceDifferentCiphertext(): void
    {
        $encryptor = new SodiumAtCredentialsEncryptor(base64_encode(random_bytes(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));

        self::assertNotSame($encryptor->encrypt('same-password'), $encryptor->encrypt('same-password'), 'A fresh random nonce must be used each time.');
    }

    public function testDecryptingWithADifferentKeyFails(): void
    {
        $encryptor = new SodiumAtCredentialsEncryptor(base64_encode(random_bytes(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
        $ciphertext = $encryptor->encrypt('a-secret-password');

        $otherEncryptor = new SodiumAtCredentialsEncryptor(base64_encode(random_bytes(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));

        $this->expectException(AtCredentialsDecryptionFailed::class);
        $otherEncryptor->decrypt($ciphertext);
    }

    public function testDecryptingGarbageFails(): void
    {
        $encryptor = new SodiumAtCredentialsEncryptor(base64_encode(random_bytes(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));

        $this->expectException(AtCredentialsDecryptionFailed::class);
        $encryptor->decrypt('not-valid-base64-ciphertext-at-all');
    }

    public function testRejectsAKeyOfTheWrongLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SodiumAtCredentialsEncryptor(base64_encode('too-short'));
    }
}
