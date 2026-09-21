<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentDraft;
use App\Fiscal\Domain\DocumentDraftId;
use App\Fiscal\Domain\DocumentDraftRepository;
use App\Fiscal\Domain\Exception\CreditNoteNotAllowed;
use App\Fiscal\Domain\Exception\DocumentNotFound;
use App\Fiscal\Domain\IssuedDocumentReader;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Decimal\Money;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use App\Shared\Domain\Tax\PriceCalculationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * technical-scope.md §7.4: "The API offers `POST /documents/{id}/credit-note`
 * to prefill a draft" from an existing document's lines — the caller edits
 * (e.g. reduces quantities for a partial credit note) and issues it
 * through the ordinary task 2.6 pipeline like any other draft.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class CreateCreditNoteDraftHandler
{
    public function __construct(
        private readonly IssuedDocumentReader $documents,
        private readonly DocumentDraftRepository $drafts,
        private readonly PriceCalculationService $priceCalculation,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateCreditNoteDraft $command): DocumentDraftId
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.issue', $companyId)) {
            throw new PermissionDenied();
        }

        $original = $this->documents->find($companyId, $command->originalDocumentId);

        if (null === $original) {
            throw new DocumentNotFound();
        }

        if ('A' === $original['status']) {
            throw CreditNoteNotAllowed::documentIsCancelled();
        }

        $alreadyCredited = $this->documents->sumGrossTotalOfActiveCreditNotesAgainst($companyId, $original['document_no']);

        if ($alreadyCredited->compareTo(Money::fromString($original['gross_total'])) >= 0) {
            throw CreditNoteNotAllowed::documentIsFullyRectified();
        }

        $payload = [
            'customer_id' => $original['customer_id'],
            'pricing_mode' => $original['pricing_mode'],
            'rounding_method' => $original['rounding_method'],
            'date' => $this->clock->now()->format('Y-m-d'),
            'references' => [[
                'referenced_document_no' => $original['document_no'],
                'reason' => $command->reason,
            ]],
            'lines' => array_map(self::toDraftLine(...), $original['lines']),
        ];

        $draftId = DocumentDraftId::generate();

        $this->drafts->save(DocumentDraft::create(
            $draftId,
            $companyId,
            'NC',
            $payload,
            $this->priceCalculation->calculate($payload),
            $command->actingUserId,
            $this->clock->now(),
        ));

        $this->auditLogger->log(
            'document_draft.created_as_credit_note',
            'DocumentDraft',
            $draftId->toString(),
            ['original_document_id' => $command->originalDocumentId->toString(), 'original_document_no' => $original['document_no']],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );

        return $draftId;
    }

    /**
     * @param array<string, mixed> $line
     *
     * @return array<string, mixed>
     */
    private static function toDraftLine(array $line): array
    {
        return [
            'product_id' => $line['product_id'],
            'product_code' => $line['product_code'],
            'description' => $line['product_description'],
            'product_type' => $line['product_type'],
            'unit_code' => $line['unit_code'],
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'discounts' => null !== $line['discount_percent']
                ? [['type' => 'percentage', 'value' => $line['discount_percent']]]
                : [],
            'tax_region' => $line['tax_region'],
            'tax_code' => $line['tax_code'],
            'exemption_reason_code' => $line['exemption_reason_code'],
        ];
    }
}
