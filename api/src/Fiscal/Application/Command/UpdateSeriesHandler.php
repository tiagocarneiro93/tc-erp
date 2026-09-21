<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\Exception\SeriesCodeAlreadyInUse;
use App\Fiscal\Domain\Exception\SeriesNotFound;
use App\Fiscal\Domain\SeriesRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateSeriesHandler
{
    public function __construct(
        private readonly SeriesRepository $series,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(UpdateSeries $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('series.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $series = $this->series->find($companyId, $command->seriesId);

        if (null === $series) {
            throw new SeriesNotFound();
        }

        $collision = $this->series->findByCode($companyId, $series->documentType(), $command->code);

        if (null !== $collision && !$collision->id()->equals($series->id())) {
            throw new SeriesCodeAlreadyInUse($series->documentType(), $command->code);
        }

        $series->update($command->code, $command->isTraining, $command->firstNumber);
        $this->series->save($series);

        $this->auditLogger->log(
            'series.updated',
            'Series',
            $command->seriesId->toString(),
            ['code' => $command->code],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
