<?php

declare(strict_types=1);

namespace App\Output\Application;

use App\Output\Domain\DocumentMailer;
use App\Output\Domain\InvalidEmailRecipient;
use App\Output\Domain\OutgoingDocumentEmail;
use App\Output\Domain\PrintableDocumentNotFound;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Fiscal\PrintableDocument;
use App\Shared\Domain\TransactionManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * docs/plans/phase-3.md task 3.7, the queued half. Two steps with different
 * failure behaviour, which is why this runs on `output.bus` (no surrounding
 * transaction) and opens its own:
 *
 *  1. Obtain the sealed PDF ({@see SealedDocumentPdfs}) in a transaction of its
 *     own, which commits the seal, its stored file and its print record
 *     *before* anything is mailed. A seal is paid for once; whatever happens to
 *     the mail afterwards, it is never made again.
 *  2. Mail it. A refusal by the mail system propagates, and Messenger retries
 *     the whole message — step 1 then just finds the stored file. An address
 *     that can never work, or a document that does not exist, is unrecoverable:
 *     retrying it would only repeat the failure.
 *
 * Delivery is at-least-once: if the process dies between a successful send and
 * the audit record, the retry mails the same sealed file again.
 */
#[AsMessageHandler(bus: 'output.bus')]
final class SendDocumentEmailHandler
{
    public function __construct(
        private readonly SealedDocumentPdfs $sealedPdfs,
        private readonly DocumentMailer $mailer,
        private readonly TransactionManager $transactions,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(SendDocumentEmail $message): void
    {
        $companyId = CompanyId::fromString($message->companyId);

        try {
            $sealed = $this->transactions->transactional(fn (): SealedPdf => $this->sealedPdfs->obtain($companyId, $message->documentId, $message->actingUserId));
        } catch (PrintableDocumentNotFound $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        $document = $sealed->document;

        try {
            $this->mailer->send(new OutgoingDocumentEmail(
                $message->recipients,
                self::subject($document),
                self::body($document, $message->message),
                $document->issuer->email,
                self::filename($document),
                $sealed->bytes,
            ));
        } catch (InvalidEmailRecipient $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        $this->transactions->transactional(fn () => $this->auditLogger->log(
            'document.email_sent',
            'Document',
            $document->id,
            ['document_no' => $document->documentNo, 'recipients' => $message->recipients, 'stored_file_id' => $sealed->file->id, 'sha256' => $sealed->file->sha256],
            $message->actingUserId,
            null,
            $message->ip,
            $message->userAgent,
        ));
    }

    private static function subject(PrintableDocument $document): string
    {
        return \sprintf('%s %s — %s', $document->documentTypeName, $document->documentNo, $document->issuer->name);
    }

    private static function body(PrintableDocument $document, ?string $note): string
    {
        $note = trim((string) $note);

        return \sprintf(
            "Exmos. Senhores,\n\nSegue em anexo o documento %s %s, emitido por %s em %s.\n\n%sCom os melhores cumprimentos,\n%s\n",
            $document->documentTypeName,
            $document->documentNo,
            $document->issuer->name,
            $document->issueDate->format('Y-m-d'),
            '' === $note ? '' : $note."\n\n",
            $document->issuer->name,
        );
    }

    private static function filename(PrintableDocument $document): string
    {
        return \sprintf('%s.pdf', preg_replace('/[^A-Za-z0-9._-]+/', '_', $document->documentNo) ?? 'document');
    }
}
