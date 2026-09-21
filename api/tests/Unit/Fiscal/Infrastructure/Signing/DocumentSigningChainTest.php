<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Infrastructure\Signing;

use App\Fiscal\Domain\Signing\SigningMessage;
use App\Fiscal\Infrastructure\Signing\OpenSslDocumentSigner;
use App\Shared\Domain\Decimal\Money;
use PHPUnit\Framework\TestCase;

/**
 * technical-scope.md §7.2/phase-2.md decision 5: since the Despacho's own
 * example private key is truncated in the PDF (byte-exact reproduction of
 * its worked example is impossible), the chain rule itself — document N's
 * `PreviousHash` is document N-1's `Hash`, each signature verifiable with
 * the series' public key — is what this test proves, against a locally
 * generated throwaway key.
 */
final class DocumentSigningChainTest extends TestCase
{
    public function testEachDocumentSignsWithThePreviousDocumentsHashAndAllVerify(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $pem));
        $details = openssl_pkey_get_details($key);
        self::assertNotFalse($details);
        self::assertIsString($details['key']);
        $publicKey = openssl_pkey_get_public($details['key']);
        self::assertNotFalse($publicKey);

        $privateKeyPath = tempnam(sys_get_temp_dir(), 'signing-key-');
        file_put_contents($privateKeyPath, $pem);
        $signer = new OpenSslDocumentSigner($privateKeyPath, 1);

        try {
            $documents = [
                ['date' => '2013-07-01', 'entryAt' => '2013-07-01T11:27:08', 'no' => 'FS 001/0001', 'total' => '100.00'],
                ['date' => '2013-07-01', 'entryAt' => '2013-07-01T11:30:00', 'no' => 'FS 001/0002', 'total' => '200.00'],
                ['date' => '2013-07-02', 'entryAt' => '2013-07-02T09:15:00', 'no' => 'FS 001/0003', 'total' => '50.00'],
            ];

            $previousHash = null;
            $messages = [];
            $signedHashes = [];

            foreach ($documents as $document) {
                $message = SigningMessage::build(
                    new \DateTimeImmutable($document['date']),
                    new \DateTimeImmutable($document['entryAt']),
                    $document['no'],
                    Money::fromString($document['total']),
                    $previousHash,
                );
                $messages[] = $message;

                $signedHash = $signer->sign($message);
                $signedHashes[] = $signedHash->hash();

                $signature = base64_decode($signedHash->hash(), true);
                self::assertNotFalse($signature);

                self::assertSame(1, openssl_verify(
                    $message,
                    $signature,
                    $publicKey,
                    \OPENSSL_ALGO_SHA1,
                ), \sprintf('Signature for "%s" must verify against the series public key.', $document['no']));

                $previousHash = $signedHash->hash();
            }

            // The first document in the series signs with an empty
            // PreviousHash (Despacho §2.1.5); every later one carries the
            // exact `Hash` its predecessor produced.
            self::assertStringEndsWith(';', $messages[0]);
            self::assertStringContainsString(';'.$signedHashes[0], $messages[1]);
            self::assertStringContainsString(';'.$signedHashes[1], $messages[2]);
            self::assertCount(3, array_unique($signedHashes), 'Each document in the chain must have a distinct signature.');
        } finally {
            @unlink($privateKeyPath);
        }
    }
}
