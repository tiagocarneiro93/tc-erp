<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtIntegration\Infrastructure\Security;

use App\AtIntegration\Infrastructure\Security\OpenSslAtRequestCipher;
use PHPUnit\Framework\TestCase;

/**
 * docs/plans/phase-3.md task 3.1d/decision 10: no AT-produced example
 * exists to golden-test against (we only have AT's *public* key — nothing
 * that decrypted a Nonce we didn't generate would prove). This proves the
 * chain is internally consistent instead: a throwaway RSA keypair stands
 * in for AT's, and an independent decrypt (not the class under test)
 * confirms what was encrypted decodes back to the original plaintext. The
 * real proof that AT itself accepts the output is task 3.1g's live call.
 */
final class OpenSslAtRequestCipherTest extends TestCase
{
    private static string $publicKeyPath;
    private static string $privateKeyPem;

    public static function setUpBeforeClass(): void
    {
        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($keyPair, 'Could not generate a throwaway RSA key pair for this test.');

        $exported = openssl_pkey_export($keyPair, $privateKeyPem);
        self::assertTrue($exported, 'Could not export the throwaway private key.');
        self::assertIsString($privateKeyPem);
        self::$privateKeyPem = $privateKeyPem;
        /** @var array{key: string} $details */
        $details = openssl_pkey_get_details($keyPair);

        $path = tempnam(sys_get_temp_dir(), 'at-public-key-');
        self::assertNotFalse($path);
        file_put_contents($path, $details['key']);
        self::$publicKeyPath = $path;
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$publicKeyPath);
    }

    public function testCredentialsAreBase64Encoded(): void
    {
        $cipher = new OpenSslAtRequestCipher(self::$publicKeyPath);

        $credentials = $cipher->buildCredentials('SenhaPF123', new \DateTimeImmutable('2026-01-01T10:00:00Z'));

        self::assertNotFalse(base64_decode($credentials->passwordBase64, true));
        self::assertNotFalse(base64_decode($credentials->nonceBase64, true));
        self::assertNotFalse(base64_decode($credentials->createdBase64, true));
    }

    public function testTwoRequestsNeverReuseTheSameKey(): void
    {
        $cipher = new OpenSslAtRequestCipher(self::$publicKeyPath);
        $now = new \DateTimeImmutable('2026-01-01T10:00:00Z');

        $first = $cipher->buildCredentials('SenhaPF123', $now);
        $second = $cipher->buildCredentials('SenhaPF123', $now);

        // Same plaintext, same timestamp — different Nonce (fresh Ks per
        // request) and therefore different Password ciphertext too.
        self::assertNotSame($first->nonceBase64, $second->nonceBase64);
        self::assertNotSame($first->passwordBase64, $second->passwordBase64);
    }

    public function testRoundTripRecoversTheOriginalPasswordAndTimestamp(): void
    {
        $cipher = new OpenSslAtRequestCipher(self::$publicKeyPath);
        $now = new \DateTimeImmutable('2026-03-15T08:30:45.123Z');

        $credentials = $cipher->buildCredentials('S3nh@ComCaracteresEspeciais!', $now);

        $key = $this->unwrapKey($credentials->nonceBase64);

        self::assertSame('S3nh@ComCaracteresEspeciais!', $this->decrypt($credentials->passwordBase64, $key));
        self::assertSame('2026-03-15T08:30:45.123Z', $this->decrypt($credentials->createdBase64, $key));
    }

    private function unwrapKey(string $nonceBase64): string
    {
        $privateKey = openssl_pkey_get_private(self::$privateKeyPem);
        self::assertNotFalse($privateKey);

        $encrypted = base64_decode($nonceBase64, true);
        self::assertIsString($encrypted);

        $unwrapped = openssl_private_decrypt($encrypted, $key, $privateKey, \OPENSSL_PKCS1_PADDING);
        self::assertTrue($unwrapped, 'Could not RSA-decrypt the Nonce with the paired private key.');
        self::assertIsString($key);

        return $key;
    }

    private function decrypt(string $base64, string $key): string
    {
        $ciphertext = base64_decode($base64, true);
        self::assertIsString($ciphertext);

        $plaintext = openssl_decrypt($ciphertext, 'aes-128-ecb', $key, \OPENSSL_RAW_DATA);
        self::assertIsString($plaintext);

        return $plaintext;
    }
}
