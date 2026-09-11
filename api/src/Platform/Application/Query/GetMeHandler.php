<?php

declare(strict_types=1);

namespace App\Platform\Application\Query;

use App\Platform\Domain\CompanyRepository;
use App\Platform\Domain\MembershipRepository;
use App\Platform\Domain\RoleRepository;
use App\Platform\Domain\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetMeHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MembershipRepository $memberships,
        private readonly CompanyRepository $companies,
        private readonly RoleRepository $roles,
    ) {
    }

    public function __invoke(GetMe $query): MeView
    {
        $user = $this->users->find($query->userId);

        if (null === $user) {
            throw new \RuntimeException('Authenticated user not found.');
        }

        $companyViews = [];
        foreach ($this->memberships->findByUser($query->userId) as $membership) {
            $company = $this->companies->find($membership->companyId());

            if (null === $company) {
                continue;
            }

            $companyViews[] = new CompanyMembershipView(
                $company->id()->toString(),
                $company->legalName(),
                $membership->role(),
                $this->roles->permissionsFor($membership->role()),
            );
        }

        return new MeView(
            $user->id()->toString(),
            $user->email(),
            $user->name(),
            $user->mustChangePassword(),
            $companyViews,
        );
    }
}
