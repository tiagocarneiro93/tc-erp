<?php

declare(strict_types=1);

namespace App\Parties\Application\Command;

use App\Parties\Domain\Exception\InvalidPaymentTermsId;
use App\Parties\Domain\Supplier;
use App\Parties\Domain\SupplierRepository;
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
final class CreateSupplierHandler
{
    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly CountryRepository $countries,
        private readonly PaymentTermsExistenceChecker $paymentTerms,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateSupplier $command): void
    {
        $companyId = $this->companyContext->companyId();

        // ADR 0002: "customers.manage — Create/edit customers and
        // suppliers" covers both party types under one permission pair.
        if (!$this->permissionChecker->isGranted('customers.manage', $companyId)) {
            throw new PermissionDenied();
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

        $now = $this->clock->now();
        $supplier = Supplier::create(
            $command->supplierId,
            $companyId,
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
            $now,
        );
        $this->suppliers->save($supplier);

        $this->auditLogger->log(
            'supplier.created',
            'Supplier',
            $command->supplierId->toString(),
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
