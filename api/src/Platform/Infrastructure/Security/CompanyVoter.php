<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Security;

use App\Platform\Domain\MembershipRepository;
use App\Platform\Domain\RoleRepository;
use App\Shared\Domain\CompanyId;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Backs both company resolution (`CompanyRouteListener`, attribute
 * {@see self::MEMBER}) and endpoint authorization (controllers voting on a
 * permission code, e.g. `documents.issue`) against the same membership data
 * (technical-scope.md §5.3, §8.1).
 *
 * @extends Voter<string, CompanyId>
 */
final class CompanyVoter extends Voter
{
    public const MEMBER = 'company.member';

    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly RoleRepository $roles,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof CompanyId;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof SecurityUser) {
            return false;
        }

        $membership = $this->memberships->find($user->user()->id(), $subject);

        if (null === $membership || 'active' !== $membership->status()) {
            return false;
        }

        if (self::MEMBER === $attribute) {
            return true;
        }

        return \in_array($attribute, $this->roles->permissionsFor($membership->role()), true);
    }
}
