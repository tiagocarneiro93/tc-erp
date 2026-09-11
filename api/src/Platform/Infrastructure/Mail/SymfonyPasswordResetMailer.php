<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Mail;

use App\Platform\Domain\PasswordResetMailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SymfonyPasswordResetMailer implements PasswordResetMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromAddress,
    ) {
    }

    public function sendResetLink(string $email, string $resetUrl): void
    {
        $message = (new Email())
            ->from($this->fromAddress)
            ->to($email)
            ->subject('Reset your tc-erp password')
            ->text(\sprintf(
                "Someone requested a password reset for this account.\n\n".
                "To choose a new password, open this link:\n%s\n\n".
                "If you didn't request this, you can ignore this email.",
                $resetUrl,
            ));

        $this->mailer->send($message);
    }
}
