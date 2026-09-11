<?php

declare(strict_types=1);

namespace App\Company\Application\Command;

use App\Company\Domain\CompanyProfileRepository;
use App\Company\Domain\Exception\CompanyProfileNotFound;
use App\Company\Domain\Exception\InvalidCountryCode;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CountryRepository;
use App\Shared\Domain\Decimal\Money;
use App\Shared\Domain\Exception\InvalidNif;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Nif;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateCompanyProfileHandler
{
    public function __construct(
        private readonly CompanyProfileRepository $profiles,
        private readonly CountryRepository $countries,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(UpdateCompanyProfile $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('company.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $profile = $this->profiles->find($companyId);

        if (null === $profile) {
            throw new CompanyProfileNotFound();
        }

        try {
            $nif = Nif::fromString($command->nif);
        } catch (\InvalidArgumentException) {
            throw new InvalidNif();
        }

        if (!$this->isKnownCountry($command->country)) {
            throw new InvalidCountryCode($command->country);
        }

        $shareCapital = null === $command->shareCapital ? null : Money::fromString($command->shareCapital);

        $profile->updateProfile(
            $nif,
            $command->legalName,
            $command->commercialName,
            $command->address,
            $command->postalCode,
            $command->city,
            $command->country,
            $shareCapital,
            $command->registryOffice,
            $command->email,
            $command->phone,
            $command->logoKey,
            $command->fiscalRegion,
            $command->vatRegime,
            $command->cashVat,
        );
        $this->profiles->save($profile);

        $this->auditLogger->log(
            'company.profile_updated',
            'CompanyProfile',
            $companyId->toString(),
            ['legal_name' => $command->legalName, 'fiscal_region' => $command->fiscalRegion],
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
