<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Mail;

use App\Platform\Domain\InvitationMailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SymfonyInvitationMailer implements InvitationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromAddress,
    ) {
    }

    public function sendInvitation(string $email, string $companyName, string $setPasswordUrl): void
    {
        $message = (new Email())
            ->from($this->fromAddress)
            ->to($email)
            ->subject(\sprintf('You have been invited to %s on tc-erp', $companyName))
            ->text(\sprintf(
                "You have been invited to join %s on tc-erp.\n\n".
                "To set your password and get started, open this link:\n%s\n\n".
                "If you weren't expecting this, you can ignore this email.",
                $companyName,
                $setPasswordUrl,
            ));

        $this->mailer->send($message);
    }
}
