<?php

declare(strict_types=1);

namespace App\Output\Application;

use App\Output\Domain\CopyLabel;
use App\Output\Domain\DocumentPdfRenderer;
use App\Output\Domain\DocumentPrint;
use App\Output\Domain\DocumentPrintKind;
use App\Output\Domain\DocumentPrintRepository;
use App\Output\Domain\ElectronicSealer;
use App\Output\Domain\PrintableDocumentNotFound;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Fiscal\PrintableDocumentReader;
use App\Shared\Domain\Output\FileArchive;
use Symfony\Component\Uid\Uuid;

/**
 * docs/plans/phase-3.md task 3.6: the sealed PDF of an issued document, made
 * **once**. The first call renders, seals and archives it (`stored_files`,
 * kind `sealed_pdf`, SHA-256); every later call hands back the stored bytes,
 * verified against that hash — never re-rendered, never re-sealed, so the
 * file a customer received and the file anyone downloads again are the same
 * one, and a seal is only ever paid for once.
 *
 * Only the "send electronically" path comes here (technical-scope.md §7.8): a
 * document that is only printed or downloaded is never sealed, and plain
 * renders ({@see RenderDocumentPdfHandler}) never touch this class.
 *
 * Must run inside a company transaction. An advisory lock per document
 * serialises concurrent first calls, so two simultaneous e-mails of the same
 * document cannot both seal and store one.
 *
 * The copy label printed in the sealed file is the one the document's print
 * log yields at the moment of sealing (the first thing ever handed out is the
 * "Original"); it is then fixed for good, being part of the signed bytes.
 * Sealing logs that hand-out in `document_prints` (kind `email`) in the same
 * transaction, which is also how the label is found again later: it is the
 * one on the document's earliest `email` row. Sending the identical sealed
 * file again is not a new rendering and logs no new row (the send itself is
 * in the audit log); and if a send then fails for good, the document still
 * counts as handed out — the safe direction, since it can only make a later
 * PDF a "copy", never a second "Original".
 */
final class SealedDocumentPdfs
{
    private const SUBJECT_TYPE = 'Document';
    private const KIND = 'sealed_pdf';

    public function __construct(
        private readonly PrintableDocumentReader $documents,
        private readonly DocumentPdfRenderer $renderer,
        private readonly ElectronicSealer $sealer,
        private readonly FileArchive $archive,
        private readonly DocumentPrintRepository $prints,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws PrintableDocumentNotFound        when no such issued document exists (a draft never does)
     * @throws \App\Output\Domain\SealingFailed when it has to be sealed and cannot be — nothing is stored, nothing unsealed is returned
     */
    public function obtain(CompanyId $companyId, string $documentId, string $actingUserId): SealedPdf
    {
        $document = $this->documents->find($companyId, $documentId) ?? throw new PrintableDocumentNotFound();

        $this->prints->lockDocument($companyId, $document->id);

        $existing = $this->archive->findBySubject($companyId, self::KIND, self::SUBJECT_TYPE, $document->id);

        if (null !== $existing) {
            $label = $this->prints->firstLabelOf($companyId, $document->id, DocumentPrintKind::Email)
                ?? throw new \LogicException(\sprintf('The sealed PDF of document %s has no print record.', $document->id));

            return new SealedPdf($this->archive->contents($companyId, $existing->id), $existing, $label, false, $document);
        }

        $copyLabel = CopyLabel::forNextCopy($this->prints->countFor($companyId, $document->id));
        $sealed = $this->sealer->seal($companyId, $this->renderer->render($document, $copyLabel));
        $archived = $this->archive->storeContents($companyId, self::KIND, self::SUBJECT_TYPE, $document->id, $sealed);

        $this->prints->add(new DocumentPrint(Uuid::v7()->toRfc4122(), $companyId, $document->id, DocumentPrintKind::Email, $copyLabel, $actingUserId, $this->clock->now()));

        return new SealedPdf($sealed, $archived, $copyLabel, true, $document);
    }
}
