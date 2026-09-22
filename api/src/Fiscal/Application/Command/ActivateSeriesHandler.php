<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\AtCommunicationQueue;
use App\Fiscal\Domain\Exception\SeriesNotFound;
use App\Fiscal\Domain\Exception\SeriesWebserviceRejected;
use App\Fiscal\Domain\SeriesRepository;
use App\Shared\Domain\AtIntegration\SeriesRegistration;
use App\Shared\Domain\AtIntegration\SeriesWebserviceClient;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * docs/plans/phase-3.md task 3.1/decision 3: registers the series with AT
 * (`registarSerie`) synchronously, right here — a low-volume, deliberate
 * action, not the per-document outbox's fire-and-forget volume. The
 * validation code entered manually in Phase 2 (technical-scope.md §7.6,
 * before this task) is gone: it comes from AT's own response now.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class ActivateSeriesHandler
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

    public function __invoke(ActivateSeries $command): void
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

        $result = $this->atClient->register($companyId, new SeriesRegistration(
            $series->code(),
            $series->isTraining(),
            $series->documentType(),
            $series->firstNumber(),
            $now,
        ));

        $this->atCommunications->recordResolved(
            $companyId,
            'series_register',
            'Series',
            $command->seriesId->toString(),
            $result->accepted ? 'accepted' : 'rejected',
            $result->responseCode,
            $result->responseMessage,
            $result->validationCode,
            $now,
        );

        if (!$result->accepted || null === $result->validationCode) {
            throw new SeriesWebserviceRejected('registarSerie', $result->responseMessage);
        }

        $series->activate($result->validationCode, $now);
        $this->series->save($series);

        $this->auditLogger->log(
            'series.activated',
            'Series',
            $command->seriesId->toString(),
            ['validation_code' => $result->validationCode],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
