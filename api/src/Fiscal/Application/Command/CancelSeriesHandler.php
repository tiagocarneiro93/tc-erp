<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\Exception\SeriesNotFound;
use App\Fiscal\Domain\SeriesRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CancelSeriesHandler
{
    public function __construct(
        private readonly SeriesRepository $series,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
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
