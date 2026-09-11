<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use App\Platform\Application\Security\PermissionChecker;
use App\Platform\Domain\Exception\NotAMember;
use App\Platform\Domain\Exception\PermissionDenied;
use App\Platform\Domain\MembershipRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RemoveMemberHandler
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(RemoveMember $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('members.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $membership = $this->memberships->find($command->targetUserId, $companyId);

        if (null === $membership) {
            throw new NotAMember();
        }

        $this->memberships->remove($membership);

        $this->auditLogger->log(
            'company.member_removed',
            'Membership',
            $command->targetUserId->toString(),
            [],
            $command->actingUserId->toString(),
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
