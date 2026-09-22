<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtIntegration\Infrastructure\Soap;

use App\AtIntegration\Domain\Security\AtRequestCipher;
use App\AtIntegration\Infrastructure\Soap\AtMutualTlsCertificate;
use App\AtIntegration\Infrastructure\Soap\SeriesWSClient;
use App\Shared\Domain\AtIntegration\SeriesRegistration;
use App\Shared\Domain\Company\AtCredentialsNotConfigured;
use App\Shared\Domain\Company\AtCredentialsProvider;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;

/**
 * The "no AT credentials configured" check happens before any `\SoapClient`
 * is ever constructed, so this runs in the plain unit suite without
 * `ext-soap` — unlike {@see \App\Tests\Integration\AtIntegration\SeriesWSClientLiveTest},
 * which needs it for the real call. Guards against a real regression: this
 * case used to throw a plain `\RuntimeException`, surfacing as a generic
 * 500 instead of the same {@see AtCredentialsNotConfigured} (404) every
 * other AT-credentials-dependent code path already uses
 * ({@see \App\Company\Application\Command\TestAtCredentialsHandler}).
 */
final class SeriesWSClientTest extends TestCase
{
    public function testRegisteringWithoutConfiguredCredentialsIsReportedAsNotConfigured(): void
    {
        $credentials = $this->createMock(AtCredentialsProvider::class);
        $credentials->method('forCompany')->willReturn(null);

        $client = new SeriesWSClient(
            'unused.wsdl',
            'https://unused.example',
            new AtMutualTlsCertificate('unused.pfx', ''),
            $this->createMock(AtRequestCipher::class),
            $credentials,
        );

        $this->expectException(AtCredentialsNotConfigured::class);

        $client->register(CompanyId::generate(), new SeriesRegistration(
            'A',
            true,
            'FT',
            1,
            new \DateTimeImmutable(),
        ));
    }
}
