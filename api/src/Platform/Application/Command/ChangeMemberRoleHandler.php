<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use App\Platform\Application\Security\PermissionChecker;
use App\Platform\Domain\Exception\NotAMember;
use App\Platform\Domain\Exception\PermissionDenied;
use App\Platform\Domain\Exception\UnknownRole;
use App\Platform\Domain\MembershipRepository;
use App\Platform\Domain\RoleRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ChangeMemberRoleHandler
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly RoleRepository $roles,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(ChangeMemberRole $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('members.manage', $companyId)) {
            throw new PermissionDenied();
        }

        if (null === $this->roles->find($command->newRole)) {
            throw new UnknownRole();
        }

        $membership = $this->memberships->find($command->targetUserId, $companyId);

        if (null === $membership) {
            throw new NotAMember();
        }

        $previousRole = $membership->role();
        $membership->changeRole($command->newRole);
        $this->memberships->save($membership);

        $this->auditLogger->log(
            'company.member_role_changed',
            'Membership',
            $command->targetUserId->toString(),
            ['previous_role' => $previousRole, 'new_role' => $command->newRole],
            $command->actingUserId->toString(),
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
