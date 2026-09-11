<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Security;

use App\Platform\Application\Security\CurrentUserId;
use App\Platform\Domain\UserId;
use Symfony\Bundle\SecurityBundle\Security;

final class SymfonyCurrentUserId implements CurrentUserId
{
    public function __construct(private readonly Security $security)
    {
    }

    public function id(): UserId
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            throw new \LogicException('There is no authenticated user in the current context.');
        }

        return $user->user()->id();
    }
}
