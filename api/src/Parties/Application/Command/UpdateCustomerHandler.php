<?php

declare(strict_types=1);

namespace App\Parties\Application\Command;

use App\Parties\Domain\CustomerRepository;
use App\Parties\Domain\Exception\CustomerNotFound;
use App\Parties\Domain\Exception\InvalidPaymentTermsId;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CountryRepository;
use App\Shared\Domain\Exception\InvalidCountryCode;
use App\Shared\Domain\Exception\InvalidNif;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Nif;
use App\Shared\Domain\PaymentTermsExistenceChecker;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateCustomerHandler
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CountryRepository $countries,
        private readonly PaymentTermsExistenceChecker $paymentTerms,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(UpdateCustomer $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('customers.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $customer = $this->customers->find($companyId, $command->customerId);

        if (null === $customer) {
            throw new CustomerNotFound();
        }

        if ('PT' === $command->country && !Nif::isValid($command->nif)) {
            throw new InvalidNif();
        }

        if (!$this->isKnownCountry($command->country)) {
            throw new InvalidCountryCode($command->country);
        }

        if (null !== $command->paymentTermsId && !$this->paymentTerms->exists($companyId, $command->paymentTermsId)) {
            throw new InvalidPaymentTermsId($command->paymentTermsId);
        }

        $customer->update(
            $command->code,
            $command->nif,
            $command->name,
            $command->address,
            $command->postalCode,
            $command->city,
            $command->country,
            $command->email,
            $command->phone,
            $command->paymentTermsId,
            $this->clock->now(),
        );
        $this->customers->save($customer);

        $this->auditLogger->log(
            'customer.updated',
            'Customer',
            $command->customerId->toString(),
            ['code' => $command->code, 'name' => $command->name],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }

    private function isKnownCountry(string $code): bool
    {
        foreach ($this->countries->findAll() as $country) {
            if ($country->code() === $code) {
                return true;
            }
        }

        return false;
    }
}
