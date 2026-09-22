<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\AtCommunicationQueue;
use App\Fiscal\Domain\Exception\SeriesAlreadyIssuedDocuments;
use App\Fiscal\Domain\Exception\SeriesNotFound;
use App\Fiscal\Domain\Exception\SeriesWebserviceRejected;
use App\Fiscal\Domain\SeriesRepository;
use App\Fiscal\Domain\SeriesStatus;
use App\Shared\Domain\AtIntegration\SeriesCancellation;
use App\Shared\Domain\AtIntegration\SeriesWebserviceClient;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * docs/plans/phase-3.md task 3.1: a `draft` series was never registered
 * with AT (task 2.2's own lifecycle — registration happens at
 * {@see ActivateSeriesHandler}), so cancelling one is purely local, no
 * `anularSerie` call. Cancelling an `active` one calls it — only reachable
 * once {@see \App\Fiscal\Domain\Series::cancel()}'s own guard confirms the
 * series never issued a document (§1.3.3).
 */
#[AsMessageHandler(bus: 'command.bus')]
final class CancelSeriesHandler
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

    public function __invoke(CancelSeries $command): void
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

        if (SeriesStatus::Active === $series->status()) {
            if (null !== $series->lastNumber()) {
                throw new SeriesAlreadyIssuedDocuments();
            }

            $validationCode = $series->validationCode() ?? throw new \LogicException('An active series must have a validation code.');

            $result = $this->atClient->cancel($companyId, new SeriesCancellation(
                $series->code(),
                $series->documentType(),
                $validationCode,
            ));

            $this->atCommunications->recordResolved(
                $companyId,
                'series_cancel',
                'Series',
                $command->seriesId->toString(),
                $result->accepted ? 'accepted' : 'rejected',
                $result->responseCode,
                $result->responseMessage,
                null,
                $now,
            );

            if (!$result->accepted) {
                throw new SeriesWebserviceRejected('anularSerie', $result->responseMessage);
            }
        }

        $series->cancel();
        $this->series->save($series);

        $this->auditLogger->log(
            'series.cancelled',
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
