<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Security;

use App\Platform\Domain\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adapts the framework-free App\Platform\Domain\User to Symfony Security,
 * which Domain must never depend on (CLAUDE.md — architecture rules).
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(private readonly User $user)
    {
    }

    public function user(): User
    {
        return $this->user;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        $email = $this->user->email();

        if ('' === $email) {
            throw new \LogicException('User email must not be empty.');
        }

        return $email;
    }

    public function getPassword(): string
    {
        return $this->user->passwordHash();
    }
}
