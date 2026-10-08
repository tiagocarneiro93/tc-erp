<?php

declare(strict_types=1);

namespace App\Tests\LiveAt\Support;

use App\Shared\Domain\Nif;

/**
 * Everything the live-AT suite is configured with, read from the environment
 * (`api/.env.test.local` — git-ignored, loaded because PHPUnit runs under
 * APP_ENV=test; see `tests/LiveAt/README.md`).
 *
 *   AT_TEST_SUBUSER, AT_TEST_PASSWORD   the AT sub-user (profiles WFA/WSE/WDT)
 *   AT_TEST_NIF                         the NIF the test documents are issued as
 *   AT_WEBSERVICES_CLIENT_CERT_PATH     the .pfx AT issued for the test environment
 *   AT_WEBSERVICES_CLIENT_CERT_PASSWORD
 *   AT_PUBLIC_KEY_PATH                  AT's public key (encrypts the SOAP password)
 *   AT_EFATURA_WEBSERVICE_ENDPOINT      must be a TEST endpoint ({@see TestEndpointGuard})
 *   AT_SERIES_WEBSERVICE_ENDPOINT       must be a TEST endpoint
 *   AT_TEST_ONLY                        optional: comma list of scenario names / document types to run
 *
 * Paths are relative to `api/` unless absolute.
 */
final class LiveAtConfig
{
    public function __construct(
        public readonly string $subuser,
        public readonly string $password,
        public readonly string $nif,
        public readonly string $clientCertPath,
        public readonly string $clientCertPassword,
        public readonly string $publicKeyPath,
        public readonly string $efaturaEndpoint,
        public readonly string $seriesEndpoint,
        public readonly string $seriesWsdlPath,
        public readonly ?string $only,
    ) {
    }

    /**
     * @param array<string, mixed> $env        normally `$_SERVER + $_ENV`
     * @param string               $projectDir the `api/` directory
     */
    public static function fromEnvironment(array $env, string $projectDir): self
    {
        $get = static function (string $name) use ($env): string {
            $value = $env[$name] ?? '';

            return \is_string($value) ? trim($value) : '';
        };

        $path = static fn (string $value): string => '' === $value || str_starts_with($value, '/') ? $value : $projectDir.'/'.$value;

        return new self(
            $get('AT_TEST_SUBUSER'),
            $get('AT_TEST_PASSWORD'),
            $get('AT_TEST_NIF'),
            $path($get('AT_WEBSERVICES_CLIENT_CERT_PATH')),
            $get('AT_WEBSERVICES_CLIENT_CERT_PASSWORD'),
            $path($get('AT_PUBLIC_KEY_PATH')),
            $get('AT_EFATURA_WEBSERVICE_ENDPOINT'),
            $get('AT_SERIES_WEBSERVICE_ENDPOINT'),
            $projectDir.'/resources/wsdl/SeriesWS.wsdl',
            '' === $get('AT_TEST_ONLY') ? null : $get('AT_TEST_ONLY'),
        );
    }

    /**
     * What is missing or unusable, one human sentence each; empty when the
     * suite can run. A wrong endpoint is not "missing" — it throws
     * ({@see assertEndpointsAreTestEndpoints()}).
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        foreach (['AT_TEST_SUBUSER' => $this->subuser, 'AT_TEST_PASSWORD' => $this->password, 'AT_TEST_NIF' => $this->nif, 'AT_WEBSERVICES_CLIENT_CERT_PATH' => $this->clientCertPath, 'AT_PUBLIC_KEY_PATH' => $this->publicKeyPath] as $name => $value) {
            if ('' === $value) {
                $problems[] = $name.' is not set';
            }
        }

        if ('' !== $this->nif && !Nif::isValid($this->nif)) {
            $problems[] = 'AT_TEST_NIF is not a valid NIF';
        }

        foreach (['the client certificate (AT_WEBSERVICES_CLIENT_CERT_PATH)' => $this->clientCertPath, 'AT\'s public key (AT_PUBLIC_KEY_PATH)' => $this->publicKeyPath] as $what => $file) {
            if ('' !== $file && !is_readable($file)) {
                $problems[] = \sprintf('%s is not readable at %s', $what, $file);
            }
        }

        return $problems;
    }

    /**
     * @throws RefusingNonTestEndpoint
     */
    public function assertEndpointsAreTestEndpoints(): void
    {
        TestEndpointGuard::assertTestEndpoint('AT_EFATURA_WEBSERVICE_ENDPOINT', $this->efaturaEndpoint);
        TestEndpointGuard::assertTestEndpoint('AT_SERIES_WEBSERVICE_ENDPOINT', $this->seriesEndpoint);
    }

    /** A soft warning, not a failure: AT sub-users are normally `<NIF>/<n>`. */
    public function subuserWarning(): ?string
    {
        return '' !== $this->nif && !str_starts_with($this->subuser, $this->nif.'/')
            ? \sprintf('AT_TEST_SUBUSER "%s" does not start with "%s/" — fine if your test sub-user belongs to another NIF, but AT may then reject documents issued as %s.', $this->subuser, $this->nif, $this->nif)
            : null;
    }
}
