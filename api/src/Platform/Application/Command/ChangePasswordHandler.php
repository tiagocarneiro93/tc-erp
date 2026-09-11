<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use App\Platform\Domain\Exception\EmptyPassword;
use App\Platform\Domain\Exception\InvalidCurrentPassword;
use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ChangePasswordHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwordHasher,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ChangePassword $command): void
    {
        if ('' === $command->newPassword) {
            throw new EmptyPassword();
        }

        $user = $this->users->find($command->userId);

        if (null === $user || !$this->passwordHasher->verify($user->passwordHash(), $command->currentPassword)) {
            throw new InvalidCurrentPassword();
        }

        $user->changePassword($this->passwordHasher->hash($command->newPassword), $this->clock->now());
        $this->users->save($user);
    }
}
