<?php

declare(strict_types=1);

namespace App\Output\Application;

use App\Output\Domain\CopyLabel;
use App\Output\Domain\DocumentPdfRenderer;
use App\Output\Domain\DocumentPrint;
use App\Output\Domain\DocumentPrintRepository;
use App\Output\Domain\PrintableDocumentNotFound;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Fiscal\PrintableDocumentReader;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * docs/plans/phase-3.md task 3.5. Every request is logged in `document_prints`
 * and the log decides the copy label: the first document handed out is the
 * "Original", every later one a copy ({@see CopyLabel}). Runs on `command.bus`,
 * inside one transaction with an advisory lock on the document, so the count,
 * the render and the log row cannot interleave with a concurrent request.
 *
 * PDFs are generated on demand and not stored (§7.8) — only the sealed ones
 * (task 3.6) are.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class RenderDocumentPdfHandler
{
    public function __construct(
        private readonly PrintableDocumentReader $documents,
        private readonly DocumentPdfRenderer $renderer,
        private readonly DocumentPrintRepository $prints,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RenderDocumentPdf $command): RenderedDocumentPdf
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.read', $companyId)) {
            throw new PermissionDenied();
        }

        if (!Uuid::isValid($command->documentId)) {
            throw new PrintableDocumentNotFound();
        }

        $document = $this->documents->find($companyId, $command->documentId) ?? throw new PrintableDocumentNotFound();

        $this->prints->lockDocument($companyId, $document->id);
        $copyLabel = CopyLabel::forNextCopy($this->prints->countFor($companyId, $document->id));

        $bytes = $this->renderer->render($document, $copyLabel);

        $this->prints->add(new DocumentPrint(
            Uuid::v7()->toRfc4122(),
            $companyId,
            $document->id,
            $command->kind,
            $copyLabel,
            $command->actingUserId,
            $this->clock->now(),
        ));

        $this->auditLogger->log(
            'document.pdf_'.$command->kind->value,
            'Document',
            $document->id,
            ['document_no' => $document->documentNo, 'copy_label' => $copyLabel],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );

        return new RenderedDocumentPdf(
            $bytes,
            \sprintf('%s.pdf', preg_replace('/[^A-Za-z0-9._-]+/', '_', $document->documentNo) ?? 'document'),
            $copyLabel,
        );
    }
}
