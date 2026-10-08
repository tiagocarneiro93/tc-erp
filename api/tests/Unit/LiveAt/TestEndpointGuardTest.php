<?php

declare(strict_types=1);

namespace App\Tests\Unit\LiveAt;

use App\Tests\LiveAt\Support\RefusingNonTestEndpoint;
use App\Tests\LiveAt\Support\TestEndpointGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TestEndpointGuardTest extends TestCase
{
    #[DataProvider('acceptedEndpoints')]
    public function testAtsPublishedTestEndpointsPass(string $url): void
    {
        TestEndpointGuard::assertTestEndpoint('X', $url);

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function acceptedEndpoints(): iterable
    {
        yield 'e-Fatura send' => ['https://servicos.portaldasfinancas.gov.pt:723/fatcorews/ws/'];
        yield 'series' => ['https://servicos.portaldasfinancas.gov.pt:722/SeriesWSService'];
        yield 'e-Fatura query' => ['https://servicos.portaldasfinancas.gov.pt:725/fatshare/ws/fatshareFaturas'];
    }

    #[DataProvider('refused')]
    public function testAnythingElseIsRefused(string $url): void
    {
        $this->expectException(RefusingNonTestEndpoint::class);
        $this->expectExceptionMessage('not one of AT\'s TEST endpoints');

        TestEndpointGuard::assertTestEndpoint('AT_EFATURA_WEBSERVICE_ENDPOINT', $url);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function refused(): iterable
    {
        yield 'production e-Fatura' => ['https://servicos.portaldasfinancas.gov.pt:423/fatcorews/ws/'];
        yield 'production series' => ['https://servicos.portaldasfinancas.gov.pt:422/SeriesWSService'];
        yield 'default https port' => ['https://servicos.portaldasfinancas.gov.pt/fatcorews/ws/'];
        yield 'plain http' => ['http://servicos.portaldasfinancas.gov.pt:723/fatcorews/ws/'];
        yield 'another host' => ['https://example.com:723/fatcorews/ws/'];
        yield 'lookalike host' => ['https://servicos.portaldasfinancas.gov.pt.evil.test:723/x'];
        yield 'empty' => [''];
        yield 'garbage' => ['not a url'];
    }
}
