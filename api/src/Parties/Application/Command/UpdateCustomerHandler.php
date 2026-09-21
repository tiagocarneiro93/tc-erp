<?php

declare(strict_types=1);

namespace App\Parties\Application\Command;

use App\Parties\Domain\Customer;
use App\Parties\Domain\CustomerRepository;
use App\Parties\Domain\Exception\CustomerNameIsLocked;
use App\Parties\Domain\Exception\CustomerNifIsLocked;
use App\Parties\Domain\Exception\CustomerNotFound;
use App\Parties\Domain\Exception\InvalidPaymentTermsId;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\CountryRepository;
use App\Shared\Domain\Exception\InvalidCountryCode;
use App\Shared\Domain\Exception\InvalidNif;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Fiscal\CustomerHasIssuedDocuments;
use App\Shared\Domain\Nif;
use App\Shared\Domain\PaymentTermsExistenceChecker;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Despacho 8632/2014 §3.3.3–3.3.5, docs/plans/phase-2.md task 2.3: once a
 * customer has at least one issued document, `nif`/`name` lock — checked
 * here, not in `Customer::update()` itself, since the entity has no way to
 * ask Fiscal a question without violating Deptrac (ADR 0004's pattern).
 */
#[AsMessageHandler(bus: 'command.bus')]
final class UpdateCustomerHandler
{
    private const FINAL_CONSUMER_NIF = '999999990';

    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CountryRepository $countries,
        private readonly PaymentTermsExistenceChecker $paymentTerms,
        private readonly CustomerHasIssuedDocuments $issuedDocuments,
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

        if ($command->name !== $customer->name() || $command->nif !== $customer->nif()) {
            $this->guardAgainstLockedFields($companyId, $customer, $command);
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

    private function guardAgainstLockedFields(CompanyId $companyId, Customer $customer, UpdateCustomer $command): void
    {
        if (!$this->issuedDocuments->forCustomer($companyId, $customer->id()->toString())) {
            return;
        }

        if ($command->name !== $customer->name()) {
            throw new CustomerNameIsLocked();
        }

        $nifWasBlankOrGeneric = '' === $customer->nif() || self::FINAL_CONSUMER_NIF === $customer->nif();

        if ($command->nif !== $customer->nif() && !$nifWasBlankOrGeneric) {
            throw new CustomerNifIsLocked();
        }
    }
}
