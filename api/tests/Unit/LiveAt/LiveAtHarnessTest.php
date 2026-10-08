<?php

declare(strict_types=1);

namespace App\Tests\Unit\LiveAt;

use App\Tests\LiveAt\Support\LiveAtHarness;
use App\Tests\LiveAt\Support\RefusingNonTestEndpoint;
use PHPUnit\Framework\TestCase;

/**
 * The wiring only (no network): with a throwaway key and certificate the real
 * adapters can be built from the environment, and nothing is built when
 * something is missing or an endpoint is not a test one.
 */
final class LiveAtHarnessTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        LiveAtHarness::reset();
        $this->dir = sys_get_temp_dir().'/at-harness-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/var', 0o700, true);
    }

    protected function tearDown(): void
    {
        LiveAtHarness::reset();
        array_map('unlink', glob($this->dir.'/*.*') ?: []);
        foreach (glob($this->dir.'/var/*/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->dir.'/var/*') ?: [] as $leftover) {
            @rmdir($leftover);
        }
        @rmdir($this->dir.'/var');
        @rmdir($this->dir);
    }

    public function testItBuildsTheRealAdaptersFromTheEnvironment(): void
    {
        [$harness, $problems] = LiveAtHarness::tryCreate($this->environment(), $this->dir);

        self::assertSame([], $problems);
        self::assertNotNull($harness);
        self::assertDirectoryExists($harness->report->directory());
        self::assertSame($harness, LiveAtHarness::tryCreate($this->environment(), $this->dir)[0], 'One instance per run: the series registry is shared.');
    }

    public function testNothingIsBuiltWhenSomethingIsMissing(): void
    {
        [$harness, $problems] = LiveAtHarness::tryCreate(['AT_EFATURA_WEBSERVICE_ENDPOINT' => 'https://servicos.portaldasfinancas.gov.pt:723/x', 'AT_SERIES_WEBSERVICE_ENDPOINT' => 'https://servicos.portaldasfinancas.gov.pt:722/y'], $this->dir);

        self::assertNull($harness);
        self::assertNotEmpty($problems);
    }

    public function testAProductionEndpointStopsEverythingBeforeAnythingIsBuilt(): void
    {
        $this->expectException(RefusingNonTestEndpoint::class);

        LiveAtHarness::tryCreate(['AT_SERIES_WEBSERVICE_ENDPOINT' => 'https://servicos.portaldasfinancas.gov.pt:422/SeriesWSService'] + $this->environment(), $this->dir);
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertInstanceOf(\OpenSSLAsymmetricKey::class, $key);
        $keyForCsr = $key; // openssl_csr_new() takes the key by reference
        $csr = openssl_csr_new(['commonName' => 'at-live-test'], $keyForCsr);
        self::assertInstanceOf(\OpenSSLCertificateSigningRequest::class, $csr);
        $certificate = openssl_csr_sign($csr, null, $key, 1);
        self::assertNotFalse($certificate);
        openssl_x509_export_to_file($certificate, $this->dir.'/at-public-key.cer');
        openssl_pkcs12_export_to_file($certificate, $this->dir.'/client.pfx', $key, 'pw');

        return [
            'AT_TEST_SUBUSER' => '508025095/1',
            'AT_TEST_PASSWORD' => 'secret',
            'AT_TEST_NIF' => '508025095',
            'AT_WEBSERVICES_CLIENT_CERT_PATH' => $this->dir.'/client.pfx',
            'AT_WEBSERVICES_CLIENT_CERT_PASSWORD' => 'pw',
            'AT_PUBLIC_KEY_PATH' => $this->dir.'/at-public-key.cer',
            'AT_EFATURA_WEBSERVICE_ENDPOINT' => 'https://servicos.portaldasfinancas.gov.pt:723/fatcorews/ws/',
            'AT_SERIES_WEBSERVICE_ENDPOINT' => 'https://servicos.portaldasfinancas.gov.pt:722/SeriesWSService',
        ];
    }
}
