<?php

declare(strict_types=1);

namespace App\Tests\LiveAt\Support;

use App\AtIntegration\Domain\Efatura\EFaturaRequestBuilder;
use App\AtIntegration\Infrastructure\Security\OpenSslAtRequestCipher;
use App\AtIntegration\Infrastructure\Soap\AtMutualTlsCertificate;
use App\AtIntegration\Infrastructure\Soap\AtSecurityHeaderBuilder;
use App\AtIntegration\Infrastructure\Soap\CurlAtSoapHttpTransport;
use App\AtIntegration\Infrastructure\Soap\EFaturaWSClient;
use App\AtIntegration\Infrastructure\Soap\SeriesWSClient;
use App\Shared\Domain\AtIntegration\SeriesRegistration;
use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Clock\SystemClock;
use PHPUnit\Framework\Assert;

/**
 * The real production adapters (`SeriesWSClient`, `EFaturaWSClient`, the real
 * request builder, cipher and mutual-TLS transport), wired by hand to the
 * environment instead of through the Symfony container and the database. One
 * instance per run, shared by every live test through {@see self::instance()}.
 */
final class LiveAtHarness
{
    private static ?self $instance = null;

    /** The documents of a run go in normal series, like in the product (training series are never communicated). */
    public const DOCUMENT_SERIES_IS_TRAINING = false;

    public readonly CompanyId $company;
    public readonly LiveAtReport $report;
    public readonly CapturingAtTransport $transport;
    public readonly EFaturaWSClient $efatura;
    public readonly SeriesWSClient $series;
    public readonly ScenarioDocumentFactory $documents;

    /** @var array<string, RegisteredSeries> by document type */
    private array $registeredSeries = [];
    /** @var array<string, string> scenario name => document number sent */
    private array $sentDocumentNos = [];

    private function __construct(public readonly LiveAtConfig $config, string $projectDir)
    {
        $this->company = CompanyId::generate();
        $this->report = new LiveAtReport($projectDir.'/var/at-live', substr(bin2hex(random_bytes(4)), 0, 6));

        $credentials = new EnvAtCredentialsProvider($config);
        $cipher = new OpenSslAtRequestCipher($config->publicKeyPath);
        $certificate = new AtMutualTlsCertificate($config->clientCertPath, $config->clientCertPassword);

        $this->transport = new CapturingAtTransport(new CurlAtSoapHttpTransport($certificate));
        $this->efatura = new EFaturaWSClient(
            $config->efaturaEndpoint,
            $this->transport,
            new AtSecurityHeaderBuilder($cipher, new SystemClock()),
            $credentials,
            // Certificate number 0: the software is not certified yet (docs/plans/phase-3.md decision 6).
            new EFaturaRequestBuilder(0),
        );
        $this->series = new SeriesWSClient($config->seriesWsdlPath, $config->seriesEndpoint, $certificate, $cipher, $credentials);
        $this->documents = new ScenarioDocumentFactory($config->nif);
    }

    /**
     * @param array<string, mixed> $env
     *
     * @return array{0: ?self, 1: list<string>} the harness (null when something is missing) and why
     *
     * @throws RefusingNonTestEndpoint when an endpoint is not an AT test endpoint — never skipped
     */
    public static function tryCreate(array $env, string $projectDir): array
    {
        $config = LiveAtConfig::fromEnvironment($env, $projectDir);
        $config->assertEndpointsAreTestEndpoints();

        $problems = $config->problems();

        return [] === $problems ? [self::$instance ??= new self($config, $projectDir), []] : [null, $problems];
    }

    /** For the harness's own unit tests; a real run keeps one instance. */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * The series for `$documentType` in this run, registered with AT on first use.
     */
    public function seriesFor(string $documentType, string $step): RegisteredSeries
    {
        if (isset($this->registeredSeries[$documentType])) {
            return $this->registeredSeries[$documentType];
        }

        $code = 'LV'.$this->report->runId.$documentType;
        $result = $this->series->register($this->company, new SeriesRegistration(
            $code,
            self::DOCUMENT_SERIES_IS_TRAINING,
            $documentType,
            1,
            new \DateTimeImmutable('today', new \DateTimeZone('UTC')),
        ));
        $this->report->record($step, 'registarSerie', \sprintf('%s series %s', $documentType, $code), $result->accepted ? 'accepted' : 'rejected', $result->responseCode, $result->responseMessage);

        Assert::assertTrue($result->accepted, \sprintf('AT refused to register series %s for %s: [%d] %s', $code, $documentType, $result->responseCode, $result->responseMessage));
        Assert::assertNotNull($result->validationCode);

        return $this->registeredSeries[$documentType] = new RegisteredSeries($documentType, $code, $result->validationCode);
    }

    public function documentNoSent(string $scenarioName): ?string
    {
        return $this->sentDocumentNos[$scenarioName] ?? null;
    }

    public function rememberSent(string $scenarioName, string $documentNo): void
    {
        $this->sentDocumentNos[$scenarioName] = $documentNo;
    }
}
