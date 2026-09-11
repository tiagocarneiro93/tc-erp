<?php

declare(strict_types=1);

namespace App\Parties\Application\Command;

use App\Parties\Domain\CustomerRepository;
use App\Parties\Domain\Exception\CustomerNotFound;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeactivateCustomerHandler
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(DeactivateCustomer $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('customers.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $customer = $this->customers->find($companyId, $command->customerId);

        if (null === $customer) {
            throw new CustomerNotFound();
        }

        $customer->deactivate($this->clock->now());
        $this->customers->save($customer);

        $this->auditLogger->log(
            'customer.deactivated',
            'Customer',
            $command->customerId->toString(),
            [],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
