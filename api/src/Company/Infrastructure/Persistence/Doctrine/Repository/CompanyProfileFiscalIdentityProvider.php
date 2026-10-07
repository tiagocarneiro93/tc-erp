<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Persistence\Doctrine\Repository;

use App\Company\Domain\CompanyProfileRepository;
use App\Shared\Domain\Company\CompanyFiscalIdentity;
use App\Shared\Domain\Company\CompanyFiscalIdentityProvider;
use App\Shared\Domain\CompanyId;

final class CompanyProfileFiscalIdentityProvider implements CompanyFiscalIdentityProvider
{
    public function __construct(private readonly CompanyProfileRepository $profiles)
    {
    }

    public function forCompany(CompanyId $companyId): ?CompanyFiscalIdentity
    {
        $profile = $this->profiles->find($companyId);

        if (null === $profile) {
            return null;
        }

        return new CompanyFiscalIdentity(
            nif: $profile->nif()->toString(),
            legalName: $profile->legalName(),
            commercialName: $profile->commercialName(),
            address: $profile->address(),
            postalCode: $profile->postalCode(),
            city: $profile->city(),
            country: $profile->country(),
            email: $profile->email(),
            phone: $profile->phone(),
            cashVat: $profile->cashVat(),
        );
    }
}
