<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use App\Platform\Domain\Exception\EmptyPassword;
use App\Platform\Domain\Exception\InvalidOrExpiredResetToken;
use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\PasswordResetTokenRepository;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ConfirmPasswordResetHandler
{
    public function __construct(
        private readonly PasswordResetTokenRepository $tokens,
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwordHasher,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ConfirmPasswordReset $command): void
    {
        if ('' === $command->newPassword) {
            throw new EmptyPassword();
        }

        $now = $this->clock->now();
        $token = $this->tokens->findByTokenHash(hash('sha256', $command->token));

        if (null === $token || !$token->isValid($now)) {
            throw new InvalidOrExpiredResetToken();
        }

        $user = $this->users->find($token->userId());

        if (null === $user) {
            throw new InvalidOrExpiredResetToken();
        }

        $user->changePassword($this->passwordHasher->hash($command->newPassword), $now);
        $this->users->save($user);

        $token->markUsed($now);
        $this->tokens->save($token);
    }
}
