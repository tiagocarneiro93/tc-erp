<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\AtCommunicationQueue;
use App\Fiscal\Domain\Exception\InvalidSeriesStatusTransition;
use App\Fiscal\Domain\Exception\SeriesHasNoIssuedDocuments;
use App\Fiscal\Domain\Exception\SeriesNotFound;
use App\Fiscal\Domain\Exception\SeriesWebserviceRejected;
use App\Fiscal\Domain\SeriesRepository;
use App\Fiscal\Domain\SeriesStatus;
use App\Shared\Domain\AtIntegration\SeriesFinalization;
use App\Shared\Domain\AtIntegration\SeriesWebserviceClient;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * docs/plans/phase-3.md task 3.1: calls AT's `finalizarSerie` synchronously
 * before closing the series locally — {@see \App\Fiscal\Domain\Series::finish()}
 * already guarantees `lastNumber` is set (§2.3.2 needs a positive
 * `seqUltimoDocEmitido`) and `validationCode` is set (only an `active`
 * series can be finished, and activation is what sets it) before this is
 * ever reached.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class FinishSeriesHandler
{
    public function __construct(
        private readonly SeriesRepository $series,
        private readonly SeriesWebserviceClient $atClient,
        private readonly AtCommunicationQueue $atCommunications,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(FinishSeries $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('series.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $series = $this->series->find($companyId, $command->seriesId);

        if (null === $series) {
            throw new SeriesNotFound();
        }

        $now = $this->clock->now();

        // Series::finish() itself throws these same exceptions, but only
        // after this handler has already spent an AT round-trip building a
        // request with a meaningless validationCode/lastIssuedNumber — check
        // here first so an already-doomed request never reaches AT at all.
        if (SeriesStatus::Active !== $series->status()) {
            throw new InvalidSeriesStatusTransition('finish', $series->status());
        }

        $lastNumber = $series->lastNumber();

        if (null === $lastNumber) {
            throw new SeriesHasNoIssuedDocuments();
        }

        // Guaranteed alongside Active status by Series::activate() itself —
        // narrowed here only for the type checker's benefit.
        $validationCode = $series->validationCode() ?? throw new \LogicException('An active series must have a validation code.');

        $result = $this->atClient->finish($companyId, new SeriesFinalization(
            $series->code(),
            $series->documentType(),
            $validationCode,
            $lastNumber,
        ));

        $this->atCommunications->recordResolved(
            $companyId,
            'series_finish',
            'Series',
            $command->seriesId->toString(),
            $result->accepted ? 'accepted' : 'rejected',
            $result->responseCode,
            $result->responseMessage,
            null,
            $now,
        );

        if (!$result->accepted) {
            throw new SeriesWebserviceRejected('finalizarSerie', $result->responseMessage);
        }

        $series->finish($now);
        $this->series->save($series);

        $this->auditLogger->log(
            'series.finished',
            'Series',
            $command->seriesId->toString(),
            [],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
