<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\PriceListRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListPriceListsHandler
{
    public function __construct(
        private readonly PriceListRepository $priceLists,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<PriceListView>
     */
    public function __invoke(ListPriceLists $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.read', $companyId)) {
            throw new PermissionDenied();
        }

        return array_map(PriceListView::fromEntity(...), $this->priceLists->findAll($companyId));
    }
}
