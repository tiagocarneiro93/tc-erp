<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\SeriesRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListSeriesHandler
{
    public function __construct(
        private readonly SeriesRepository $series,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<SeriesView>
     */
    public function __invoke(ListSeries $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('series.manage', $companyId)) {
            throw new PermissionDenied();
        }

        return array_map(
            SeriesView::fromEntity(...),
            $this->series->findAll($companyId),
        );
    }
}
