<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Security;

use App\Shared\Domain\Security\CurrentActorId;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Shared cross-module twin of `SymfonyCurrentUserId` (docs/decisions/0004):
 * same token unwrapping, returned as a plain string since modules outside
 * Platform have no business depending on the `UserId` value object.
 */
final class SymfonyCurrentActorId implements CurrentActorId
{
    public function __construct(private readonly Security $security)
    {
    }

    public function id(): string
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            throw new \LogicException('There is no authenticated user in the current context.');
        }

        return $user->user()->id()->toString();
    }
}
