<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentTypeRepository;
use App\Fiscal\Domain\Exception\SeriesCodeAlreadyInUse;
use App\Fiscal\Domain\Exception\UnknownDocumentType;
use App\Fiscal\Domain\Series;
use App\Fiscal\Domain\SeriesRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateSeriesHandler
{
    public function __construct(
        private readonly SeriesRepository $series,
        private readonly DocumentTypeRepository $documentTypes,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(CreateSeries $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('series.manage', $companyId)) {
            throw new PermissionDenied();
        }

        if (null === $this->documentTypes->find($command->documentType)) {
            throw new UnknownDocumentType($command->documentType);
        }

        if (null !== $this->series->findByCode($companyId, $command->documentType, $command->code)) {
            throw new SeriesCodeAlreadyInUse($command->documentType, $command->code);
        }

        $this->series->save(Series::create(
            $command->seriesId,
            $companyId,
            $command->documentType,
            $command->code,
            $command->isTraining,
            $command->firstNumber,
        ));

        $this->auditLogger->log(
            'series.created',
            'Series',
            $command->seriesId->toString(),
            ['document_type' => $command->documentType, 'code' => $command->code],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
