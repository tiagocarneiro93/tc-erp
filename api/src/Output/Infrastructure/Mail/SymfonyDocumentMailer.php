<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Mail;

use App\Output\Domain\DocumentMailer;
use App\Output\Domain\DocumentMailFailed;
use App\Output\Domain\InvalidEmailRecipient;
use App\Output\Domain\OutgoingDocumentEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * Sent from the platform's own address (a company's domain would fail SPF/DKIM
 * alignment); the issuing company's address goes in Reply-To so an answer
 * reaches them.
 */
final class SymfonyDocumentMailer implements DocumentMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromAddress,
    ) {
    }

    public function send(OutgoingDocumentEmail $email): void
    {
        try {
            $message = (new Email())
                ->from($this->fromAddress)
                ->to(...$email->recipients)
                ->subject($email->subject)
                ->text($email->textBody)
                ->attach($email->attachmentBytes, $email->attachmentFilename, 'application/pdf');

            if (null !== $email->replyTo && '' !== trim($email->replyTo)) {
                $message->replyTo($email->replyTo);
            }

            $this->mailer->send($message);
        } catch (RfcComplianceException $e) {
            throw new InvalidEmailRecipient($e->getMessage(), 0, $e);
        } catch (TransportExceptionInterface $e) {
            throw new DocumentMailFailed($e->getMessage(), 0, $e);
        }
    }
}
