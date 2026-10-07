<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Application;

use App\Output\Application\SealedDocumentPdfs;
use App\Output\Application\SendDocumentEmail;
use App\Output\Application\SendDocumentEmailHandler;
use App\Output\Domain\DocumentMailer;
use App\Output\Domain\DocumentMailFailed;
use App\Output\Domain\InvalidEmailRecipient;
use App\Output\Domain\OutgoingDocumentEmail;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Fiscal\PrintableDocument;
use App\Shared\Domain\Fiscal\PrintableDocumentReader;
use App\Shared\Domain\TransactionManager;
use App\Tests\Support\Output\CountingRenderer;
use App\Tests\Support\Output\CountingSealer;
use App\Tests\Support\Output\MemoryArchive;
use App\Tests\Support\Output\MemoryPrints;
use App\Tests\Support\Output\RecordingAudit;
use App\Tests\Support\PrintableDocuments;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * The queued half of "e-mail a document": seal once, mail, and survive a
 * mail system that says no.
 */
final class SendDocumentEmailHandlerTest extends TestCase
{
    private CompanyId $company;
    private PrintableDocument $document;
    private CountingSealer $sealer;
    private MemoryArchive $archive;
    private MemoryPrints $prints;
    private ScriptedMailer $mailer;
    private RecordingAudit $audit;
    private SendDocumentEmailHandler $handler;

    protected function setUp(): void
    {
        $this->company = CompanyId::generate();
        $this->document = PrintableDocuments::invoice();
        $this->sealer = new CountingSealer();
        $this->archive = new MemoryArchive();
        $this->prints = new MemoryPrints();
        $this->mailer = new ScriptedMailer();
        $this->audit = new RecordingAudit();
        $document = $this->document;
        $reader = new class($document) implements PrintableDocumentReader {
            public function __construct(private readonly PrintableDocument $document)
            {
            }

            public function find(CompanyId $companyId, string $documentId): ?PrintableDocument
            {
                return $documentId === $this->document->id ? $this->document : null;
            }
        };
        $clock = new class implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-07T10:00:00+00:00');
            }
        };
        $transactions = new class implements TransactionManager {
            public function transactional(\Closure $operation): mixed
            {
                return $operation();
            }
        };
        $sealed = new SealedDocumentPdfs($reader, new CountingRenderer(), $this->sealer, $this->archive, $this->prints, $clock);
        $this->handler = new SendDocumentEmailHandler($sealed, $this->mailer, $transactions, $this->audit);
    }

    public function testItMailsTheSealedPdfWithASubjectAndBodyInPortuguese(): void
    {
        ($this->handler)($this->message(['cliente@example.pt'], 'Obrigado!'));

        self::assertCount(1, $this->mailer->sent);
        $mail = $this->mailer->sent[0];
        self::assertSame(['cliente@example.pt'], $mail->recipients);
        self::assertSame('Fatura FT 2026A/1 — Empresa Exportadora, Lda', $mail->subject);
        self::assertStringContainsString('Segue em anexo o documento Fatura FT 2026A/1', $mail->textBody);
        self::assertStringContainsString("Obrigado!\n\nCom os melhores cumprimentos", $mail->textBody);
        self::assertSame('geral@empresa.pt', $mail->replyTo);
        self::assertSame('FT_2026A_1.pdf', $mail->attachmentFilename);
        self::assertSame('sealed(pdf:Original)', $mail->attachmentBytes);
    }

    public function testASuccessfulSendIsAuditedWithTheStoredFileItCarried(): void
    {
        ($this->handler)($this->message(['cliente@example.pt']));

        self::assertCount(1, $this->audit->entries);
        self::assertSame('document.email_sent', $this->audit->entries[0]['action']);
        self::assertSame(['cliente@example.pt'], $this->audit->entries[0]['data']['recipients']);
        self::assertSame(hash('sha256', 'sealed(pdf:Original)'), $this->audit->entries[0]['data']['sha256']);
    }

    public function testAMailSystemFailureIsRetriedWithoutSealingAgain(): void
    {
        $this->mailer->failTimes = 2;
        $message = $this->message(['cliente@example.pt']);

        for ($attempt = 1; $attempt <= 2; ++$attempt) {
            try {
                ($this->handler)($message);
                self::fail('The mail system was down.');
            } catch (DocumentMailFailed) {
                self::assertSame(1, $this->sealer->calls, 'Attempt '.$attempt.': the seal already exists.');
                self::assertCount(1, $this->archive->files);
            }
        }

        ($this->handler)($message);

        self::assertSame(1, $this->sealer->calls);
        self::assertCount(1, $this->mailer->sent);
        self::assertCount(1, $this->prints->rows, 'One hand-out, however many attempts it took.');
        self::assertCount(1, $this->audit->entries, 'Only the delivery is audited, not the failed attempts.');
    }

    public function testAnAddressThatCanNeverWorkIsNotRetried(): void
    {
        $this->mailer->invalid = true;

        $this->expectException(UnrecoverableMessageHandlingException::class);

        ($this->handler)($this->message(['nonsense']));
    }

    public function testADocumentThatDoesNotExistIsNotRetried(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);

        ($this->handler)(new SendDocumentEmail($this->company->toString(), '0192e0f0-0000-7000-8000-00000000dead', ['a@example.pt'], null, 'user-1', '127.0.0.1', 'test'));
    }

    /**
     * @param non-empty-list<string> $recipients
     */
    private function message(array $recipients, ?string $note = null): SendDocumentEmail
    {
        return new SendDocumentEmail($this->company->toString(), $this->document->id, $recipients, $note, 'user-1', '127.0.0.1', 'test');
    }
}

final class ScriptedMailer implements DocumentMailer
{
    /** @var list<OutgoingDocumentEmail> */
    public array $sent = [];
    public int $failTimes = 0;
    public bool $invalid = false;

    public function send(OutgoingDocumentEmail $email): void
    {
        if ($this->invalid) {
            throw new InvalidEmailRecipient('bad address');
        }

        if ($this->failTimes > 0) {
            --$this->failTimes;

            throw new DocumentMailFailed('smtp down');
        }

        $this->sent[] = $email;
    }
}
