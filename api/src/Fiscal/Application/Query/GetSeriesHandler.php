<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\Exception\SeriesNotFound;
use App\Fiscal\Domain\SeriesRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetSeriesHandler
{
    public function __construct(
        private readonly SeriesRepository $series,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    public function __invoke(GetSeries $query): SeriesView
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('series.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $series = $this->series->find($companyId, $query->seriesId);

        if (null === $series) {
            throw new SeriesNotFound();
        }

        return SeriesView::fromEntity($series);
    }
}
