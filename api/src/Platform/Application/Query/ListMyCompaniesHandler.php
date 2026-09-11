<?php

declare(strict_types=1);

namespace App\Platform\Application\Query;

use App\Platform\Domain\CompanyRepository;
use App\Platform\Domain\MembershipRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListMyCompaniesHandler
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly CompanyRepository $companies,
    ) {
    }

    /**
     * @return list<MyCompanyView>
     */
    public function __invoke(ListMyCompanies $query): array
    {
        $views = [];

        foreach ($this->memberships->findByUser($query->userId) as $membership) {
            $company = $this->companies->find($membership->companyId());

            if (null === $company) {
                continue;
            }

            $views[] = new MyCompanyView(
                $company->id()->toString(),
                $company->nif()->toString(),
                $company->legalName(),
                $membership->role(),
            );
        }

        return $views;
    }
}
