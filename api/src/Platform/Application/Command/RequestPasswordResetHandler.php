<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use App\Platform\Domain\PasswordResetMailer;
use App\Platform\Domain\PasswordResetToken;
use App\Platform\Domain\PasswordResetTokenId;
use App\Platform\Domain\PasswordResetTokenRepository;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Always succeeds from the caller's point of view whether or not the email
 * matches a user, so the endpoint can't be used to enumerate accounts.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class RequestPasswordResetHandler
{
    private const TOKEN_TTL = 'PT1H';

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetTokenRepository $tokens,
        private readonly PasswordResetMailer $mailer,
        private readonly Clock $clock,
        private readonly string $frontendUrl,
    ) {
    }

    public function __invoke(RequestPasswordReset $command): void
    {
        $user = $this->users->findByEmail($command->email);

        if (null === $user) {
            return;
        }

        $now = $this->clock->now();
        $rawToken = bin2hex(random_bytes(32));

        $this->tokens->save(PasswordResetToken::issue(
            PasswordResetTokenId::generate(),
            $user->id(),
            $this->hash($rawToken),
            $now->add(new \DateInterval(self::TOKEN_TTL)),
            $now,
        ));

        $resetUrl = \sprintf('%s/reset-password?token=%s', rtrim($this->frontendUrl, '/'), $rawToken);
        $this->mailer->sendResetLink($user->email(), $resetUrl);
    }

    private function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
