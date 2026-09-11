<?php

declare(strict_types=1);

namespace App\Company\Application\Query;

use App\Company\Domain\CompanyProfileRepository;
use App\Company\Domain\Exception\CompanyProfileNotFound;
use App\Shared\Domain\Company\CompanyContext;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetCompanyProfileHandler
{
    public function __construct(
        private readonly CompanyProfileRepository $profiles,
        private readonly CompanyContext $companyContext,
    ) {
    }

    public function __invoke(GetCompanyProfile $query): CompanyProfileView
    {
        $profile = $this->profiles->find($this->companyContext->companyId());

        if (null === $profile) {
            throw new CompanyProfileNotFound();
        }

        return new CompanyProfileView(
            $profile->nif()->toString(),
            $profile->legalName(),
            $profile->commercialName(),
            $profile->address(),
            $profile->postalCode(),
            $profile->city(),
            $profile->country(),
            $profile->shareCapital()?->toString(),
            $profile->registryOffice(),
            $profile->email(),
            $profile->phone(),
            $profile->logoKey(),
            $profile->fiscalRegion(),
            $profile->vatRegime(),
            $profile->cashVat(),
        );
    }
}
