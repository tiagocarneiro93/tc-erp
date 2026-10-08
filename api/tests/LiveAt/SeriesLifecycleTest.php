<?php

declare(strict_types=1);

namespace App\Tests\LiveAt;

use App\Shared\Domain\AtIntegration\SeriesCancellation;
use App\Shared\Domain\AtIntegration\SeriesFinalization;
use App\Shared\Domain\AtIntegration\SeriesRegistration;

/**
 * Series communication against AT's test environment
 * (`at-ws-series-aspetos-especificos.pdf`): register, cancel, finalise.
 *
 * **Fixed on purpose.** These steps assert AT's exact answers, so nothing in
 * them is configurable: the series is a training series of type FT starting at
 * 1 today, and the expected codes are the manual's own — 2001 registered
 * (§1.5), 2003 cancelled (§1.6), 2004 finalised (§2.3.4). Only the series
 * code changes, because AT refuses a code it has seen: `LV<run><type>`.
 */
final class SeriesLifecycleTest extends LiveAtTestCase
{
    private const FIXED_DOCUMENT_TYPE = 'FT';
    private const FIXED_START_NUMBER = 1;
    private const FIXED_REGISTERED = 2001;
    private const FIXED_CANCELLED = 2003;
    private const FIXED_FINALISED = 2004;

    public function testARegisteredSeriesCanBeCancelledWhileNothingWasIssued(): void
    {
        $harness = $this->harness();
        $this->requireSoapExtension();

        $registered = $this->register('series-cancel', 'C');
        $result = $harness->series->cancel($harness->company, new SeriesCancellation($registered['code'], self::FIXED_DOCUMENT_TYPE, $registered['validation']));
        $harness->report->record('series-cancel', 'anularSerie', $registered['code'], $result->accepted ? 'accepted' : 'rejected', $result->responseCode, $result->responseMessage);

        self::assertSame(self::FIXED_CANCELLED, $result->responseCode, 'anularSerie: '.$result->responseMessage);
    }

    public function testASeriesThatIssuedDocumentsCanBeFinalised(): void
    {
        $harness = $this->harness();
        $this->requireSoapExtension();

        $registered = $this->register('series-finish', 'F');

        // AT's rule 4047: the last number must be *greater* than the start of the sequence.
        // Probe the boundary first (recorded, not asserted) — the product allows finishing at the first number.
        $probe = $harness->series->finish($harness->company, new SeriesFinalization($registered['code'], self::FIXED_DOCUMENT_TYPE, $registered['validation'], self::FIXED_START_NUMBER));
        $harness->report->record('series-finish (probe)', 'finalizarSerie', $registered['code'].' last=1', $probe->accepted ? 'accepted' : 'rejected', $probe->responseCode, $probe->responseMessage);

        if (self::FIXED_FINALISED === $probe->responseCode) {
            $this->addToAssertionCount(1);

            return; // already finalised at the first number; nothing more to prove
        }

        $result = $harness->series->finish($harness->company, new SeriesFinalization($registered['code'], self::FIXED_DOCUMENT_TYPE, $registered['validation'], self::FIXED_START_NUMBER + 1));
        $harness->report->record('series-finish', 'finalizarSerie', $registered['code'].' last=2', $result->accepted ? 'accepted' : 'rejected', $result->responseCode, $result->responseMessage);

        self::assertSame(self::FIXED_FINALISED, $result->responseCode, 'finalizarSerie: '.$result->responseMessage);
    }

    /**
     * @return array{code: string, validation: string}
     */
    private function register(string $step, string $suffix): array
    {
        $harness = $this->harness();
        $code = 'LV'.$harness->report->runId.$suffix;

        $result = $harness->series->register($harness->company, new SeriesRegistration(
            $code,
            true,
            self::FIXED_DOCUMENT_TYPE,
            self::FIXED_START_NUMBER,
            new \DateTimeImmutable('today', new \DateTimeZone('UTC')),
        ));
        $harness->report->record($step, 'registarSerie', $code, $result->accepted ? 'accepted' : 'rejected', $result->responseCode, $result->responseMessage);

        self::assertSame(self::FIXED_REGISTERED, $result->responseCode, 'registarSerie: '.$result->responseMessage);
        self::assertNotNull($result->validationCode);
        self::assertNotSame('', $result->validationCode);

        return ['code' => $code, 'validation' => $result->validationCode];
    }
}
