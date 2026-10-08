<?php

declare(strict_types=1);

namespace App\Tests\LiveAt;

use App\AtIntegration\Domain\Webservice\AtCommunicationOutcome;
use App\Shared\Domain\AtIntegration\AtCommunicableDocument;
use App\Tests\LiveAt\Support\DocumentScenario;
use App\Tests\LiveAt\Support\LiveAtHarness;
use App\Tests\LiveAt\Support\ScenarioCatalog;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every document scenario (`scenarios.dist.php`, overridable in
 * `scenarios.local.php`) sent to the e-Fatura test webservice, each in a series
 * registered for the run. By default AT must accept it; a scenario can instead
 * say `'expect' => 'rejected'` (see `FT-wrong-totals`). The series itself is
 * not configurable — see {@see SeriesLifecycleTest}.
 */
final class DocumentCommunicationTest extends LiveAtTestCase
{
    /**
     * @return iterable<string, array{0: ?DocumentScenario}>
     */
    public static function scenarios(): iterable
    {
        /** @var array<string, mixed> $env */
        $env = $_SERVER + $_ENV;
        $only = $env['AT_TEST_ONLY'] ?? null;
        $scenarios = ScenarioCatalog::load(__DIR__, \is_string($only) && '' !== trim($only) ? trim($only) : null);

        foreach ($scenarios as $scenario) {
            yield $scenario->name => [$scenario];
        }

        if ([] === $scenarios) {
            yield 'no scenario selected' => [null];
        }
    }

    #[DataProvider('scenarios')]
    public function testScenario(?DocumentScenario $scenario): void
    {
        if (null === $scenario) {
            self::markTestSkipped('AT_TEST_ONLY / scenarios.local.php selected no scenario.');
        }

        $harness = $this->harness();
        $this->requireSoapExtension(); // the document's series is registered through the series webservice

        $references = [];
        foreach ($scenario->references as $referenced) {
            $number = $harness->documentNoSent($referenced);

            if (null === $number) {
                self::markTestSkipped(\sprintf('"%s" references "%s", which was not sent in this run (filtered out, or it failed).', $scenario->name, $referenced));
            }

            $references[] = $number;
        }

        $series = $harness->seriesFor($scenario->documentType, $scenario->name.' (series)');
        $document = $harness->documents->build($scenario, $series, $references, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $detail = \sprintf('%s gross %s', $document->documentNo, $document->grossTotal);

        $operation = 'SalesInvoices' === $document->saftSection ? 'RegisterInvoice' : 'RegisterWork';
        $outcome = $harness->efatura->register($harness->company, $document);
        $this->file($harness, $scenario->name, $operation, $detail, $outcome);

        if (AtCommunicationOutcome::STATUS_ACCEPTED === $outcome->status) {
            $harness->rememberSent($scenario->name, $document->documentNo);
        }

        self::assertContains($outcome->status, 'rejected' === $scenario->expect ? [AtCommunicationOutcome::STATUS_REJECTED] : [AtCommunicationOutcome::STATUS_ACCEPTED], \sprintf(
            '%s: AT answered [%s] %s (expected: %s).',
            $scenario->name,
            $outcome->responseCode ?? 'no code',
            $outcome->responseMessage,
            $scenario->expect,
        ));

        if (null === $scenario->thenChangeStatusTo || AtCommunicationOutcome::STATUS_ACCEPTED !== $outcome->status) {
            return;
        }

        $changed = $harness->efatura->changeStatus($harness->company, $this->withStatus($document, $scenario->thenChangeStatusTo));
        $this->file($harness, $scenario->name, 'ChangeWorkStatus', $detail.' → '.$scenario->thenChangeStatusTo, $changed);

        self::assertSame(AtCommunicationOutcome::STATUS_ACCEPTED, $changed->status, \sprintf('%s: ChangeWorkStatus answered [%s] %s.', $scenario->name, $changed->responseCode ?? 'no code', $changed->responseMessage));
    }

    private function file(LiveAtHarness $harness, string $scenario, string $operation, string $detail, AtCommunicationOutcome $outcome): void
    {
        $harness->report->record($scenario, $operation, $detail, $outcome->status, $outcome->responseCode, $outcome->responseMessage);

        if (null !== $harness->transport->lastRequest && null !== $harness->transport->lastResponse) {
            $harness->report->saveExchange($scenario, $operation, $harness->transport->lastRequest, $harness->transport->lastResponse);
        }
    }

    private function withStatus(AtCommunicableDocument $document, string $status): AtCommunicableDocument
    {
        return new AtCommunicableDocument(
            $document->id,
            $document->documentType,
            $document->saftSection,
            $document->documentNo,
            $document->atcud,
            $document->issuerNif,
            $document->customerTaxId,
            $document->customerCountry,
            $document->issueDate,
            $document->systemEntryAt,
            $status,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            $document->hashCharacters,
            $document->cashVatScheme,
            $document->netTotal,
            $document->taxTotal,
            $document->grossTotal,
            $document->referencedDocumentNos,
            $document->lines,
            $document->taxSummary,
        );
    }
}
