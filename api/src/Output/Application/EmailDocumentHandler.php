<?php

declare(strict_types=1);

namespace App\Output\Application;

use App\Output\Domain\DocumentEmailDispatcher;
use App\Output\Domain\NoEmailRecipient;
use App\Output\Domain\PrintableDocumentNotFound;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Fiscal\PrintableDocumentReader;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * docs/plans/phase-3.md task 3.7, the request half: checks that there is an
 * issued document to send and somebody to send it to, records the request, and
 * queues the work. Sealing and mailing happen in {@see SendDocumentEmailHandler}
 * once this transaction has committed — a draft never gets this far, it has no
 * `documents` row.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class EmailDocumentHandler
{
    public function __construct(
        private readonly PrintableDocumentReader $documents,
        private readonly DocumentEmailDispatcher $dispatcher,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(EmailDocument $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.issue', $companyId)) {
            throw new PermissionDenied();
        }

        if (!Uuid::isValid($command->documentId)) {
            throw new PrintableDocumentNotFound();
        }

        $document = $this->documents->find($companyId, $command->documentId) ?? throw new PrintableDocumentNotFound();

        $recipients = array_values(array_unique($command->recipients));

        if ([] === $recipients) {
            $customerEmail = trim((string) $document->customer->email);
            $recipients = '' === $customerEmail ? [] : [$customerEmail];
        }

        if ([] === $recipients) {
            throw new NoEmailRecipient();
        }

        $this->auditLogger->log(
            'document.email_requested',
            'Document',
            $document->id,
            ['document_no' => $document->documentNo, 'recipients' => $recipients],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );

        $this->dispatcher->dispatchAfterCommit($companyId, $document->id, $recipients, $command->message, $command->actingUserId, $command->ip, $command->userAgent);
    }
}
