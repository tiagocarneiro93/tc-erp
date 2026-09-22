<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Fake;

use App\Shared\Domain\AtIntegration\SeriesCancellation;
use App\Shared\Domain\AtIntegration\SeriesFinalization;
use App\Shared\Domain\AtIntegration\SeriesRegistration;
use App\Shared\Domain\AtIntegration\SeriesWebserviceClient;
use App\Shared\Domain\AtIntegration\SeriesWebserviceResult;
use App\Shared\Domain\CompanyId;

/**
 * docs/plans/phase-3.md task 3.1f: bound in place of
 * {@see \App\AtIntegration\Infrastructure\Soap\SeriesWSClient} for
 * `APP_ENV=test` (`config/services_test.yaml`) — the same "fake adapter
 * for dev/tests, provider adapter for real" split the plan already calls
 * for on `ElectronicSealer` (task 3.6), applied here too: the regular test
 * suite can't depend on a live SOAP call to a government test server
 * (network flakiness, speed, side effects on AT's own test-series
 * registry) any more than it can depend on a real qualified seal
 * provider. Always succeeds, with response codes/messages matching
 * `at-ws-series-aspetos-especificos.pdf`'s own success codes (2001/2003/2004)
 * so anything asserting on them stays realistic.
 */
final class FakeSeriesWebserviceClient implements SeriesWebserviceClient
{
    public function register(CompanyId $companyId, SeriesRegistration $request): SeriesWebserviceResult
    {
        return new SeriesWebserviceResult(
            accepted: true,
            responseCode: 2001,
            responseMessage: 'Série registada com sucesso (fake, test environment).',
            validationCode: strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
        );
    }

    public function finish(CompanyId $companyId, SeriesFinalization $request): SeriesWebserviceResult
    {
        return new SeriesWebserviceResult(
            accepted: true,
            responseCode: 2004,
            responseMessage: 'Série finalizada com sucesso (fake, test environment).',
        );
    }

    public function cancel(CompanyId $companyId, SeriesCancellation $request): SeriesWebserviceResult
    {
        return new SeriesWebserviceResult(
            accepted: true,
            responseCode: 2003,
            responseMessage: 'Série anulada com sucesso (fake, test environment).',
        );
    }
}
