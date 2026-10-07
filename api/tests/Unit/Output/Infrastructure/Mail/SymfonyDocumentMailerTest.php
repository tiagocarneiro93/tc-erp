<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Infrastructure\Mail;

use App\Output\Domain\DocumentMailFailed;
use App\Output\Domain\InvalidEmailRecipient;
use App\Output\Domain\OutgoingDocumentEmail;
use App\Output\Infrastructure\Mail\SymfonyDocumentMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class SymfonyDocumentMailerTest extends TestCase
{
    public function testItSendsFromThePlatformWithTheCompanyAsReplyToAndThePdfAttached(): void
    {
        $mailer = new class implements MailerInterface {
            public ?RawMessage $sent = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent = $message;
            }
        };

        (new SymfonyDocumentMailer($mailer, 'no-reply@tc-erp.example'))->send(new OutgoingDocumentEmail(['a@example.pt', 'b@example.pt'], 'Fatura FT 1', 'Olá', 'geral@empresa.pt', 'FT_1.pdf', '%PDF-body'));

        $sent = $mailer->sent;
        self::assertInstanceOf(Email::class, $sent);
        self::assertSame('no-reply@tc-erp.example', $sent->getFrom()[0]->getAddress());
        self::assertSame(['a@example.pt', 'b@example.pt'], array_map(static fn ($a) => $a->getAddress(), $sent->getTo()));
        self::assertSame('geral@empresa.pt', $sent->getReplyTo()[0]->getAddress());
        self::assertSame('%PDF-body', $sent->getAttachments()[0]->getBody());
        self::assertSame('application/pdf', $sent->getAttachments()[0]->getMediaType().'/'.$sent->getAttachments()[0]->getMediaSubtype());
    }

    public function testAMalformedAddressCanNeverWork(): void
    {
        $this->expectException(InvalidEmailRecipient::class);

        (new SymfonyDocumentMailer($this->failingWith(new \LogicException('unused')), 'no-reply@tc-erp.example'))
            ->send(new OutgoingDocumentEmail(['not an address'], 's', 't', null, 'f.pdf', 'x'));
    }

    public function testATransportFailureIsRetryable(): void
    {
        $this->expectException(DocumentMailFailed::class);
        $this->expectExceptionMessage('smtp down');

        (new SymfonyDocumentMailer($this->failingWith(new TransportException('smtp down')), 'no-reply@tc-erp.example'))
            ->send(new OutgoingDocumentEmail(['a@example.pt'], 's', 't', null, 'f.pdf', 'x'));
    }

    private function failingWith(\Throwable $failure): MailerInterface
    {
        return new class($failure) implements MailerInterface {
            public function __construct(private readonly \Throwable $failure)
            {
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw $this->failure;
            }
        };
    }
}
