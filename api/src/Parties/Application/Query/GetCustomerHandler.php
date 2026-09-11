<?php

declare(strict_types=1);

namespace App\Parties\Application\Query;

use App\Parties\Domain\CustomerRepository;
use App\Parties\Domain\Exception\CustomerNotFound;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetCustomerHandler
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    public function __invoke(GetCustomer $query): CustomerView
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('customers.read', $companyId)) {
            throw new PermissionDenied();
        }

        $customer = $this->customers->find($companyId, $query->customerId);

        if (null === $customer) {
            throw new CustomerNotFound();
        }

        return CustomerView::fromEntity($customer);
    }
}
