<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\PriceList;
use App\Catalog\Domain\PriceListRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreatePriceListHandler
{
    public function __construct(
        private readonly PriceListRepository $priceLists,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(CreatePriceList $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $this->priceLists->save(new PriceList($command->priceListId, $companyId, $command->name, $command->defaultIncludesVat));

        $this->auditLogger->log(
            'price_list.created',
            'PriceList',
            $command->priceListId->toString(),
            ['name' => $command->name],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
