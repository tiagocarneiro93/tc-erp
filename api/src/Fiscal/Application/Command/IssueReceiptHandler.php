<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentId;
use App\Fiscal\Domain\DocumentTypeRepository;
use App\Fiscal\Domain\Exception\DocumentNotFound;
use App\Fiscal\Domain\Exception\ReceiptAllocationNotAllowed;
use App\Fiscal\Domain\Exception\SeriesCannotIssue;
use App\Fiscal\Domain\Exception\SeriesNotFound;
use App\Fiscal\Domain\Exception\UnknownDocumentType;
use App\Fiscal\Domain\IssuedDocumentReader;
use App\Fiscal\Domain\ReceiptId;
use App\Fiscal\Domain\ReceiptWriter;
use App\Fiscal\Domain\SeriesId;
use App\Fiscal\Domain\SeriesRepository;
use App\Fiscal\Domain\Signing\AtcudBuilder;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Decimal\Money;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Fiscal\CustomerSnapshotProvider;
use App\Shared\Domain\Security\PermissionChecker;
use App\Shared\Domain\TransactionManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * docs/plans/phase-2.md task 2.9: receipts (RG) are `Payments/Payment` in
 * the SAF-T XSD, not a sales-invoice variant — their own issuance path,
 * not {@see IssueDraftHandler}, though it shares that handler's series-
 * locking/chronology/ATCUD mechanics (task 2.6, all generic on
 * {@see \App\Fiscal\Domain\Series}). Never signed (Despacho 8632/2014
 * §2.2.3) — no {@see \App\Fiscal\Domain\Signing\DocumentSigner} call, no
 * hash, no hash-chain to carry on the RG series either.
 *
 * QR code generation is deliberately not implemented here yet: DL 28/2019
 * Art. 2.º(b) makes receipts a "documento fiscalmente relevante" and
 * Portaria 195/2020 Art. 6.º requires the QR on those, but `at-qrcode-spec.pdf`'s
 * field Q ("4 caracteres do Hash") is illustrated only with signed
 * documents — the spec never says what an unsigned document's QR should
 * carry there. Guessing a fiscal barcode format is exactly what CLAUDE.md
 * forbids, so this is a genuine open question for the owner, not a gap
 * this task can close alone — flagged in docs/PLAN.md's task 2.9 entry.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class IssueReceiptHandler
{
    public function __construct(
        private readonly SeriesRepository $seriesRepository,
        private readonly DocumentTypeRepository $documentTypes,
        private readonly IssuedDocumentReader $issuedDocuments,
        private readonly ReceiptWriter $receiptWriter,
        private readonly CustomerSnapshotProvider $customerSnapshots,
        private readonly TransactionManager $transactions,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(IssueReceipt $command): ReceiptId
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.issue', $companyId)) {
            throw new PermissionDenied();
        }

        if ([] === $command->allocations) {
            throw ReceiptAllocationNotAllowed::noAllocations();
        }

        try {
            $seriesId = SeriesId::fromString($command->seriesId);
        } catch (\InvalidArgumentException) {
            throw new SeriesNotFound();
        }

        $receiptId = ReceiptId::generate();

        $this->transactions->transactional(fn () => $this->issue($companyId, $seriesId, $receiptId, $command));

        return $receiptId;
    }

    private function issue(CompanyId $companyId, SeriesId $seriesId, ReceiptId $receiptId, IssueReceipt $command): void
    {
        $series = $this->seriesRepository->findForUpdate($companyId, $seriesId);

        if (null === $series || 'RG' !== $series->documentType()) {
            throw new SeriesNotFound();
        }

        if (!$series->canIssue()) {
            throw new SeriesCannotIssue($series->status());
        }

        $now = $this->clock->now();
        $total = Money::zero();
        $allocationRows = [];

        foreach ($command->allocations as $index => $allocation) {
            try {
                $documentId = DocumentId::fromString($allocation['document_id']);
            } catch (\InvalidArgumentException) {
                // A malformed id is indistinguishable from an unknown one
                // to the caller — same reasoning as CreditNoteController's
                // own parseId().
                throw new DocumentNotFound();
            }

            $document = $this->issuedDocuments->find($companyId, $documentId);

            if (null === $document) {
                throw new DocumentNotFound();
            }

            if ('A' === $document['status']) {
                throw ReceiptAllocationNotAllowed::documentIsCancelled($index);
            }

            $documentType = $this->documentTypes->find($document['document_type']);

            if (null === $documentType) {
                throw new UnknownDocumentType($document['document_type']);
            }

            if ('debit' !== $documentType->accountEffect()) {
                throw ReceiptAllocationNotAllowed::documentDoesNotCreateAReceivable($index, $documentType->code());
            }

            $amount = Money::fromString($allocation['amount']);
            $openAmount = Money::fromString($document['gross_total'])->minus($this->issuedDocuments->sumSettledAmount($companyId, $documentId));

            if ($amount->compareTo($openAmount) > 0) {
                throw ReceiptAllocationNotAllowed::exceedsOpenAmount($index);
            }

            $total = $total->plus($amount);
            $allocationRows[] = [
                'receipt_id' => $receiptId->toString(),
                'document_id' => $documentId->toString(),
                'amount' => $amount->toString(),
                'settlement_amount' => $amount->toString(),
            ];
        }

        $number = $series->nextNumber();
        $documentNo = \sprintf('RG %s/%d', $series->code(), $number);
        $atcud = AtcudBuilder::build((string) $series->validationCode(), $number);

        $series->recordIssuance($number, null, $now, $now);
        $this->seriesRepository->save($series);

        $customerSnapshot = null !== $command->customerId
            ? ($this->customerSnapshots->snapshot($companyId, $command->customerId) ?? throw new \RuntimeException('Customer referenced by this receipt no longer exists.'))
            : ['nif' => '999999990', 'name' => 'Consumidor final'];

        $receipt = [
            'id' => $receiptId->toString(),
            'series_id' => $seriesId->toString(),
            'number' => $number,
            'document_no' => $documentNo,
            'atcud' => $atcud,
            'issue_date' => $now->format('Y-m-d H:i:sP'),
            'system_entry_at' => $now->format('Y-m-d H:i:sP'),
            'customer_id' => $command->customerId,
            'customer_snapshot' => json_encode($customerSnapshot, \JSON_THROW_ON_ERROR),
            'total' => $total->toString(),
            'payment_method' => $command->paymentMethod,
            'status' => 'N',
            'status_at' => $now->format('Y-m-d H:i:sP'),
            'status_reason' => null,
            'source_id' => $command->actingUserId,
        ];

        $this->receiptWriter->insert($companyId, $receipt, $allocationRows);

        $this->auditLogger->log(
            'receipt.issued',
            'Receipt',
            $receiptId->toString(),
            ['document_no' => $documentNo, 'total' => $total->toString()],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
