<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Application\Exception\DraftValidationFailed;
use App\Fiscal\Application\Query\DraftValidationError;
use App\Fiscal\Application\Query\ValidateDraft;
use App\Fiscal\Domain\DocumentDraft;
use App\Fiscal\Domain\DocumentDraftRepository;
use App\Fiscal\Domain\DocumentId;
use App\Fiscal\Domain\DocumentType;
use App\Fiscal\Domain\DocumentTypeRepository;
use App\Fiscal\Domain\DocumentWriter;
use App\Fiscal\Domain\Exception\DocumentDraftNotFound;
use App\Fiscal\Domain\Exception\DraftMissingSeries;
use App\Fiscal\Domain\Exception\DraftNotCalculableAtIssuance;
use App\Fiscal\Domain\Exception\InvalidDraftLineForIssuance;
use App\Fiscal\Domain\Exception\SeriesCannotIssue;
use App\Fiscal\Domain\Exception\SeriesNotFound;
use App\Fiscal\Domain\Exception\UnknownDocumentType;
use App\Fiscal\Domain\SeriesId;
use App\Fiscal\Domain\SeriesRepository;
use App\Fiscal\Domain\Signing\AtcudBuilder;
use App\Fiscal\Domain\Signing\DocumentSigner;
use App\Fiscal\Domain\Signing\PrintedHashMention;
use App\Fiscal\Domain\Signing\QrPayloadBuilder;
use App\Fiscal\Domain\Signing\QrPayloadInput;
use App\Fiscal\Domain\Signing\QrRegionalTaxAmounts;
use App\Fiscal\Domain\Signing\SigningMessage;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Decimal\Money;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Fiscal\CustomerSnapshotProvider;
use App\Shared\Domain\Fiscal\IssuerSnapshotProvider;
use App\Shared\Domain\Fiscal\ProductSnapshotProvider;
use App\Shared\Domain\Security\PermissionChecker;
use App\Shared\Domain\Tax\PriceCalculationService;
use App\Shared\Domain\TransactionManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * technical-scope.md §7.1, docs/plans/phase-2.md task 2.6: the only code
 * path that may INSERT into `documents` — series lock, chronology,
 * canonical calculation, signing, ATCUD/QR, the family of inserts, series
 * update, audit log, AT-communication row, and draft deletion, all in one
 * transaction (steps 3–14; step 1's idempotency replay is handled by
 * `IdempotencyKeyGuard` wrapping the controller, before this handler ever
 * runs; step 15's async side effects are Phase 3).
 */
#[AsMessageHandler(bus: 'command.bus')]
final class IssueDraftHandler
{
    use HandleTrait;

    private const TEMPLATE_VERSION = 'v1';
    private const REGION_ORDER = ['PT', 'PT-AC', 'PT-MA'];

    public function __construct(
        private readonly DocumentDraftRepository $drafts,
        private readonly DocumentTypeRepository $documentTypes,
        private readonly SeriesRepository $seriesRepository,
        private readonly PriceCalculationService $priceCalculation,
        private readonly DocumentSigner $signer,
        private readonly DocumentWriter $documentWriter,
        private readonly CustomerSnapshotProvider $customerSnapshots,
        private readonly IssuerSnapshotProvider $issuerSnapshots,
        private readonly ProductSnapshotProvider $productSnapshots,
        private readonly TransactionManager $transactions,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
        MessageBusInterface $queryBus,
        #[Autowire(param: 'app.document_signing_certificate_number')]
        private readonly string $certificateNumber,
    ) {
        $this->messageBus = $queryBus;
    }

    public function __invoke(IssueDraft $command): DocumentId
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.issue', $companyId)) {
            throw new PermissionDenied();
        }

        $draft = $this->drafts->find($companyId, $command->draftId);

        if (null === $draft) {
            throw new DocumentDraftNotFound();
        }

        /** @var list<DraftValidationError> $errors */
        $errors = $this->handle(new ValidateDraft($command->draftId));

        if ([] !== $errors) {
            throw new DraftValidationFailed($errors);
        }

        $seriesIdString = $draft->seriesId();

        if (null === $seriesIdString) {
            throw new DraftMissingSeries();
        }

        $seriesId = SeriesId::fromString($seriesIdString);

        $documentType = $this->documentTypes->find($draft->documentType());

        if (null === $documentType) {
            throw new UnknownDocumentType($draft->documentType());
        }

        $documentId = DocumentId::generate();

        $this->transactions->transactional(fn () => $this->issue($companyId, $draft, $seriesId, $documentType, $documentId, $command));

        return $documentId;
    }

    private function issue(CompanyId $companyId, DocumentDraft $draft, SeriesId $seriesId, DocumentType $documentType, DocumentId $documentId, IssueDraft $command): void
    {
        $series = $this->seriesRepository->findForUpdate($companyId, $seriesId);

        if (null === $series) {
            throw new SeriesNotFound();
        }

        if (!$series->canIssue()) {
            throw new SeriesCannotIssue($series->status());
        }

        $now = $this->clock->now();

        $payload = $draft->payload();
        $payload['date'] = $now->format('Y-m-d');

        $calculation = $this->priceCalculation->calculate($payload);

        if (null === $calculation) {
            throw new DraftNotCalculableAtIssuance();
        }

        /**
         * @var array{
         *     lines: list<array<string, mixed>>,
         *     tax_summary: list<array{tax_region: string, tax_code: string, tax_percentage: string, taxable_base: string, tax_amount: string}>,
         *     net_total: string,
         *     tax_total: string,
         *     gross_total: string,
         *     settlement_total: string,
         * } $calculation
         */
        $number = $series->nextNumber();
        $documentNo = \sprintf('%s %s/%d', $documentType->code(), $series->code(), $number);

        $message = SigningMessage::build($now, $now, $documentNo, Money::fromString($calculation['gross_total']), $series->lastHash());
        $signed = $this->signer->sign($message);
        $hashControl = PrintedHashMention::build($signed->hash(), $this->certificateNumber);
        $atcud = AtcudBuilder::build((string) $series->validationCode(), $number);

        $series->recordIssuance($number, $signed->hash(), $now, $now);
        $this->seriesRepository->save($series);

        $customerId = $draft->customerId();
        $customerSnapshot = null !== $customerId
            ? ($this->customerSnapshots->snapshot($companyId, $customerId) ?? throw new \RuntimeException('Customer referenced by this draft no longer exists.'))
            : ['nif' => '999999990', 'name' => 'Consumidor final'];
        $issuerSnapshot = $this->issuerSnapshots->snapshot($companyId);

        $qrPayload = QrPayloadBuilder::build(new QrPayloadInput(
            issuerNif: $this->stringOrDefault($issuerSnapshot['nif'] ?? null, ''),
            customerNif: $this->stringOrDefault($customerSnapshot['nif'] ?? null, '999999990'),
            customerCountry: $this->stringOrDefault($customerSnapshot['country'] ?? null, 'PT'),
            documentTypeCode: $documentType->code(),
            documentStatus: 'N',
            documentDate: $now,
            documentNumber: $documentNo,
            atcud: $atcud,
            regionalTaxAmounts: $this->buildRegionalTaxAmounts($calculation['tax_summary']),
            taxNotIndicated: [] === $calculation['tax_summary'],
            notSubjectToVat: null,
            stampDuty: null,
            totalTaxes: Money::fromString($calculation['tax_total']),
            grossTotal: Money::fromString($calculation['gross_total']),
            withholding: null,
            hashFourChars: PrintedHashMention::fourCharacters($signed->hash()),
            certificateNumber: $this->certificateNumber,
            otherInformation: null,
        ));

        $document = [
            'id' => $documentId->toString(),
            'document_type' => $documentType->code(),
            'series_id' => $seriesId->toString(),
            'number' => $number,
            'document_no' => $documentNo,
            'atcud' => $atcud,
            'issue_date' => $now->format('Y-m-d H:i:sP'),
            'system_entry_at' => $now->format('Y-m-d H:i:sP'),
            'customer_id' => $customerId,
            'customer_snapshot' => json_encode($customerSnapshot, \JSON_THROW_ON_ERROR),
            'issuer_snapshot' => json_encode($issuerSnapshot, \JSON_THROW_ON_ERROR),
            'template_version' => self::TEMPLATE_VERSION,
            'pricing_mode' => \is_string($payload['pricing_mode'] ?? null) ? $payload['pricing_mode'] : 'net',
            'rounding_method' => \is_string($payload['rounding_method'] ?? null) ? $payload['rounding_method'] : 'per_line',
            'currency' => 'EUR',
            'exchange_rate' => null,
            'global_discount_percent' => \is_string($payload['global_discount_percent'] ?? null) ? $payload['global_discount_percent'] : null,
            'settlement_total' => $calculation['settlement_total'],
            'net_total' => $calculation['net_total'],
            'tax_total' => $calculation['tax_total'],
            'gross_total' => $calculation['gross_total'],
            'withholding_total' => null,
            'payment_terms' => isset($payload['payment_terms']) ? json_encode($payload['payment_terms'], \JSON_THROW_ON_ERROR) : null,
            'due_date' => \is_string($payload['due_date'] ?? null) ? $payload['due_date'] : null,
            'hash' => $signed->hash(),
            'hash_control' => $hashControl,
            'qr_payload' => $qrPayload,
            'is_training' => $series->isTraining(),
            'status' => 'N',
            'status_at' => $now->format('Y-m-d H:i:sP'),
            'status_reason' => null,
            'source_id' => $command->actingUserId,
            'issued_via' => 'api',
            'idempotency_key' => $command->idempotencyKey,
        ];

        $lines = $this->buildLines($companyId, $documentId, $payload, $calculation);
        $taxSummary = $this->buildTaxSummary($documentId, $calculation);
        $references = $this->buildReferences($documentId, $payload);
        $statusEvent = [
            'id' => Uuid::v7()->toRfc4122(),
            'document_id' => $documentId->toString(),
            'status' => 'N',
            'reason' => null,
            'user_id' => $command->actingUserId,
            'occurred_at' => $now->format('Y-m-d H:i:sP'),
        ];

        $this->documentWriter->insert($companyId, $document, $lines, $taxSummary, $references, $statusEvent);

        $this->auditLogger->log(
            'document.issued',
            'Document',
            $documentId->toString(),
            ['document_no' => $documentNo, 'document_type' => $documentType->code()],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );

        $this->drafts->remove($draft);
    }

    /**
     * @param array<string, mixed>                     $payload
     * @param array{lines: list<array<string, mixed>>} $calculation
     *
     * @return list<array<string, mixed>>
     */
    private function buildLines(CompanyId $companyId, DocumentId $documentId, array $payload, array $calculation): array
    {
        /** @var list<array<string, mixed>> $rawLines */
        $rawLines = $payload['lines'];
        $rows = [];

        foreach ($rawLines as $index => $rawLine) {
            $calculated = $calculation['lines'][$index];
            $productId = \is_string($rawLine['product_id'] ?? null) ? $rawLine['product_id'] : null;
            $productSnapshot = null !== $productId ? $this->productSnapshots->snapshot($companyId, $productId) : null;

            $rows[] = [
                'id' => Uuid::v7()->toRfc4122(),
                'document_id' => $documentId->toString(),
                'line_number' => $index + 1,
                'product_id' => $productId,
                'product_code' => $productSnapshot['code'] ?? $this->requireLineString($rawLine, 'product_code', $index),
                'product_description' => $productSnapshot['description'] ?? $this->requireLineString($rawLine, 'description', $index),
                'product_type' => $productSnapshot['type'] ?? $this->requireLineString($rawLine, 'product_type', $index),
                'unit_code' => $productSnapshot['unit_code'] ?? $this->requireLineString($rawLine, 'unit_code', $index),
                'quantity' => $this->requireLineString($rawLine, 'quantity', $index),
                'unit_price' => $this->requireLineString($rawLine, 'unit_price', $index),
                'discount_percent' => $this->firstPercentageDiscount($rawLine),
                'discount_amount' => $calculated['discount_amount'],
                'settlement_amount' => $calculated['settlement_amount'],
                'net_amount' => $calculated['net_amount'],
                'gross_amount' => $calculated['gross_amount'],
                'tax_region' => $calculated['tax_region'],
                'tax_code' => $calculated['tax_code'],
                'tax_percentage' => $calculated['tax_percentage'],
                'tax_amount' => $calculated['tax_amount'],
                'exemption_reason_code' => $calculated['exemption_reason_code'],
                'exemption_reason_text' => null,
                'tax_point_date' => null,
                'origin_references' => null,
            ];
        }

        return $rows;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return \is_string($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $rawLine
     */
    private function requireLineString(array $rawLine, string $field, int $index): string
    {
        $value = $rawLine[$field] ?? null;

        if (!\is_string($value) || '' === $value) {
            throw new InvalidDraftLineForIssuance($index, $field);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $rawLine
     */
    private function firstPercentageDiscount(array $rawLine): ?string
    {
        foreach ((array) ($rawLine['discounts'] ?? []) as $discount) {
            if (\is_array($discount) && 'percentage' === ($discount['type'] ?? null) && \is_string($discount['value'] ?? null)) {
                return $discount['value'];
            }
        }

        return null;
    }

    /**
     * @param array{tax_summary: list<array<string, mixed>>} $calculation
     *
     * @return list<array<string, mixed>>
     */
    private function buildTaxSummary(DocumentId $documentId, array $calculation): array
    {
        return array_map(
            static fn (array $entry): array => [
                'document_id' => $documentId->toString(),
                'tax_region' => $entry['tax_region'],
                'tax_code' => $entry['tax_code'],
                'tax_percentage' => $entry['tax_percentage'],
                'taxable_base' => $entry['taxable_base'],
                'tax_amount' => $entry['tax_amount'],
            ],
            $calculation['tax_summary'],
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private function buildReferences(DocumentId $documentId, array $payload): array
    {
        $references = [];

        foreach ((array) ($payload['references'] ?? []) as $reference) {
            if (!\is_array($reference) || !\is_string($reference['referenced_document_no'] ?? null)) {
                continue;
            }

            $references[] = [
                'document_id' => $documentId->toString(),
                'referenced_document_no' => $reference['referenced_document_no'],
                'reason' => \is_string($reference['reason'] ?? null) ? $reference['reason'] : null,
            ];
        }

        return $references;
    }

    /**
     * `at-qrcode-spec.pdf` §4: I/J/K groups, PT first then the autonomous
     * regions, whichever are actually present — the exact SAF-T `tax_code`
     * values seeded (task 1.2) are the only ones this maps.
     *
     * @param list<array{tax_region: string, tax_code: string, taxable_base: string, tax_amount: string}> $taxSummary
     *
     * @return list<QrRegionalTaxAmounts>
     */
    private function buildRegionalTaxAmounts(array $taxSummary): array
    {
        $byRegion = [];

        foreach ($taxSummary as $entry) {
            $byRegion[$entry['tax_region']][$entry['tax_code']] = $entry;
        }

        $groups = [];

        foreach (self::REGION_ORDER as $region) {
            if (!isset($byRegion[$region])) {
                continue;
            }

            $entries = $byRegion[$region];
            $groups[] = new QrRegionalTaxAmounts(
                region: $region,
                exemptBase: isset($entries['ISE']) ? Money::fromString($entries['ISE']['taxable_base']) : null,
                reducedBase: isset($entries['RED']) ? Money::fromString($entries['RED']['taxable_base']) : null,
                reducedTax: isset($entries['RED']) ? Money::fromString($entries['RED']['tax_amount']) : null,
                intermediateBase: isset($entries['INT']) ? Money::fromString($entries['INT']['taxable_base']) : null,
                intermediateTax: isset($entries['INT']) ? Money::fromString($entries['INT']['tax_amount']) : null,
                normalBase: isset($entries['NOR']) ? Money::fromString($entries['NOR']['taxable_base']) : null,
                normalTax: isset($entries['NOR']) ? Money::fromString($entries['NOR']['tax_amount']) : null,
            );
        }

        return $groups;
    }
}
