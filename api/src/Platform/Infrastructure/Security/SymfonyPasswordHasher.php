<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Security;

use App\Platform\Domain\PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private readonly PasswordHasherFactoryInterface $factory)
    {
    }

    public function hash(string $plainPassword): string
    {
        return $this->factory->getPasswordHasher(SecurityUser::class)->hash($plainPassword);
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        return $this->factory->getPasswordHasher(SecurityUser::class)->verify($hashedPassword, $plainPassword);
    }
}
