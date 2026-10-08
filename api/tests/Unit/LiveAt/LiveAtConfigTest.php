<?php

declare(strict_types=1);

namespace App\Tests\Unit\LiveAt;

use App\Tests\LiveAt\Support\LiveAtConfig;
use App\Tests\LiveAt\Support\RefusingNonTestEndpoint;
use PHPUnit\Framework\TestCase;

final class LiveAtConfigTest extends TestCase
{
    private const GOOD = [
        'AT_TEST_SUBUSER' => '508025095/1',
        'AT_TEST_PASSWORD' => 'secret',
        'AT_TEST_NIF' => '508025095',
        'AT_WEBSERVICES_CLIENT_CERT_PATH' => 'composer.json',
        'AT_WEBSERVICES_CLIENT_CERT_PASSWORD' => 'pw',
        'AT_PUBLIC_KEY_PATH' => 'composer.json',
        'AT_EFATURA_WEBSERVICE_ENDPOINT' => 'https://servicos.portaldasfinancas.gov.pt:723/fatcorews/ws/',
        'AT_SERIES_WEBSERVICE_ENDPOINT' => 'https://servicos.portaldasfinancas.gov.pt:722/SeriesWSService',
    ];

    public function testAFullyConfiguredEnvironmentHasNoProblems(): void
    {
        $config = LiveAtConfig::fromEnvironment(self::GOOD, \dirname(__DIR__, 3));

        self::assertSame([], $config->problems());
        $config->assertEndpointsAreTestEndpoints();
        self::assertNull($config->subuserWarning());
        self::assertNull($config->only);
    }

    public function testRelativePathsResolveAgainstTheApiDirectoryAndAbsoluteOnesAreKept(): void
    {
        $relative = LiveAtConfig::fromEnvironment(['AT_WEBSERVICES_CLIENT_CERT_PATH' => 'var/at-webservices/x.pfx', 'AT_PUBLIC_KEY_PATH' => '/abs/key.cer'], '/project/api');

        self::assertSame('/project/api/var/at-webservices/x.pfx', $relative->clientCertPath);
        self::assertSame('/abs/key.cer', $relative->publicKeyPath);
        self::assertSame('/project/api/resources/wsdl/SeriesWS.wsdl', $relative->seriesWsdlPath);
    }

    public function testEveryMissingThingIsListedAtOnce(): void
    {
        $problems = LiveAtConfig::fromEnvironment([], '/project/api')->problems();

        self::assertCount(5, $problems);
        self::assertStringContainsString('AT_TEST_SUBUSER', implode("\n", $problems));
        self::assertStringContainsString('AT_TEST_NIF', implode("\n", $problems));
        self::assertStringContainsString('AT_PUBLIC_KEY_PATH', implode("\n", $problems));
    }

    public function testAnInvalidNifAndUnreadableFilesAreProblems(): void
    {
        $problems = LiveAtConfig::fromEnvironment(['AT_TEST_NIF' => '123456788'] + ['AT_WEBSERVICES_CLIENT_CERT_PATH' => 'nope.pfx', 'AT_PUBLIC_KEY_PATH' => 'nope.cer'] + self::GOOD, \dirname(__DIR__, 3))->problems();
        $text = implode("\n", $problems);

        self::assertStringContainsString('not a valid NIF', $text);
        self::assertStringContainsString('client certificate', $text);
        self::assertStringContainsString('public key', $text);
    }

    public function testAProductionEndpointIsNeverASkipItIsRefused(): void
    {
        $config = LiveAtConfig::fromEnvironment(['AT_EFATURA_WEBSERVICE_ENDPOINT' => 'https://servicos.portaldasfinancas.gov.pt:423/fatcorews/ws/'] + self::GOOD, \dirname(__DIR__, 3));

        $this->expectException(RefusingNonTestEndpoint::class);

        $config->assertEndpointsAreTestEndpoints();
    }

    public function testASubuserOfAnotherNifGetsAWarningNotAFailure(): void
    {
        $config = LiveAtConfig::fromEnvironment(['AT_TEST_SUBUSER' => '999999999/1'] + self::GOOD, \dirname(__DIR__, 3));

        self::assertSame([], $config->problems());
        self::assertStringContainsString('does not start with "508025095/"', (string) $config->subuserWarning());
    }
}
