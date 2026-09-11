<?php

declare(strict_types=1);

namespace App\Platform\Application\Query;

use App\Platform\Domain\MembershipRepository;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Company\CompanyContext;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListCompanyMembersHandler
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly UserRepository $users,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<CompanyMemberView>
     */
    public function __invoke(ListCompanyMembers $query): array
    {
        $companyId = $this->companyContext->companyId();
        $views = [];

        foreach ($this->memberships->findByCompany($companyId) as $membership) {
            $user = $this->users->find($membership->userId());

            if (null === $user) {
                continue;
            }

            $views[] = new CompanyMemberView($user->id()->toString(), $user->email(), $user->name(), $membership->role());
        }

        return $views;
    }
}
