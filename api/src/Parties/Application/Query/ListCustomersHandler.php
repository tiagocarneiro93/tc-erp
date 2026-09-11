<?php

declare(strict_types=1);

namespace App\Parties\Application\Query;

use App\Parties\Domain\CustomerRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListCustomersHandler
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<CustomerView>
     */
    public function __invoke(ListCustomers $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('customers.read', $companyId)) {
            throw new PermissionDenied();
        }

        return array_map(
            CustomerView::fromEntity(...),
            $this->customers->search($companyId, $query->search),
        );
    }
}
