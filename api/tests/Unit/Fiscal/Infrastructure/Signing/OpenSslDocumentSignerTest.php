<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Infrastructure\Signing;

use App\Fiscal\Infrastructure\Signing\OpenSslDocumentSigner;
use PHPUnit\Framework\TestCase;

final class OpenSslDocumentSignerTest extends TestCase
{
    private string $privateKeyPath;
    private \OpenSSLAsymmetricKey $publicKey;

    protected function setUp(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $pem));

        $details = openssl_pkey_get_details($key);
        self::assertNotFalse($details);
        self::assertIsString($details['key']);
        $publicKey = openssl_pkey_get_public($details['key']);
        self::assertNotFalse($publicKey);
        $this->publicKey = $publicKey;

        $this->privateKeyPath = tempnam(sys_get_temp_dir(), 'signing-key-');
        file_put_contents($this->privateKeyPath, $pem);
    }

    protected function tearDown(): void
    {
        @unlink($this->privateKeyPath);
    }

    public function testSignsWithTheKeyVersionItWasConfiguredWith(): void
    {
        $signer = new OpenSslDocumentSigner($this->privateKeyPath, 3);

        self::assertSame(3, $signer->sign('2013-07-01;2013-07-01T11:27:08;FS 001/0009;200.00;')->keyVersion());
    }

    public function testProducesA172ByteBase64Signature(): void
    {
        // Despacho 8632/2014 §7.1: "deve ser garantido que as assinaturas
        // contêm 172 bytes, sem quaisquer carateres separadores de
        // linhas" — the fixed output size of an RSA-1024 signature,
        // base-64 encoded.
        $signer = new OpenSslDocumentSigner($this->privateKeyPath, 1);

        $hash = $signer->sign('2013-07-01;2013-07-01T11:27:08;FS 001/0009;200.00;');

        self::assertSame(172, \strlen($hash->hash()));
        self::assertStringNotContainsString("\n", $hash->hash());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $hash->hash());
    }

    public function testTheSignatureVerifiesAgainstTheCorrespondingPublicKey(): void
    {
        $signer = new OpenSslDocumentSigner($this->privateKeyPath, 1);
        $message = '2013-07-01;2013-07-01T11:27:08;FS 001/0009;200.00;';

        $signature = base64_decode($signer->sign($message)->hash(), true);
        self::assertNotFalse($signature);

        self::assertSame(1, openssl_verify($message, $signature, $this->publicKey, \OPENSSL_ALGO_SHA1));
    }

    public function testSigningIsDeterministic(): void
    {
        // RSA + PKCS#1 v1.5 padding has no random salt (unlike PSS), so
        // signing the same message with the same key must always produce
        // the same signature — required for AT's own re-verification and
        // for our own regression coverage of this adapter.
        $signer = new OpenSslDocumentSigner($this->privateKeyPath, 1);
        $message = '2013-07-01;2013-07-01T11:27:08;FS 001/0009;200.00;';

        self::assertSame($signer->sign($message)->hash(), $signer->sign($message)->hash());
    }

    public function testThrowsWhenTheKeyFileDoesNotExist(): void
    {
        $signer = new OpenSslDocumentSigner('/nonexistent/path/key.pem', 1);

        $this->expectException(\RuntimeException::class);
        $signer->sign('irrelevant');
    }

    public function testThrowsWhenTheKeyFileIsNotAValidPrivateKey(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'not-a-key-');
        file_put_contents($path, 'this is not a PEM key');

        try {
            $signer = new OpenSslDocumentSigner($path, 1);

            $this->expectException(\RuntimeException::class);
            $signer->sign('irrelevant');
        } finally {
            @unlink($path);
        }
    }
}
