<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentConversionRules;
use App\Fiscal\Domain\DocumentDraft;
use App\Fiscal\Domain\DocumentDraftId;
use App\Fiscal\Domain\DocumentDraftRepository;
use App\Fiscal\Domain\Exception\ConversionNotAllowed;
use App\Fiscal\Domain\Exception\DocumentNotFound;
use App\Fiscal\Domain\IssuedDocumentReader;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Decimal\Quantity;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use App\Shared\Domain\Tax\PriceCalculationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * technical-scope.md §6.8: "Conversion flows create a new draft prefilled
 * from the source document" — the caller then edits quantities down for a
 * partial conversion and issues it through the ordinary task 2.6 pipeline,
 * exactly like task 2.7's credit-note flow. Only the paths this phase can
 * actually support are allowed ({@see self::ALLOWED_TARGETS} — the full
 * `OR → NE → GR/GT → FT` chain needs stock-movement documents, Phase 5).
 */
#[AsMessageHandler(bus: 'command.bus')]
final class CreateConversionDraftHandler
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

    public function __invoke(CreateConversionDraft $command): DocumentDraftId
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.issue', $companyId)) {
            throw new PermissionDenied();
        }

        $source = $this->documents->find($companyId, $command->sourceDocumentId);

        if (null === $source) {
            throw new DocumentNotFound();
        }

        $sourceType = $source['document_type'];
        $allowedTargets = DocumentConversionRules::allowedTargets($sourceType);

        if ([] === $allowedTargets) {
            throw ConversionNotAllowed::sourceTypeNotConvertible($sourceType);
        }

        if ('A' === $source['status']) {
            throw ConversionNotAllowed::documentIsCancelled();
        }

        if (!\in_array($command->targetDocumentType, $allowedTargets, true)) {
            throw ConversionNotAllowed::targetTypeNotAllowed($sourceType, $command->targetDocumentType);
        }

        $lines = array_values(array_filter(
            array_map(
                static fn (array $line): ?array => self::toDraftLine($line, $source['document_no']),
                $source['lines'],
            ),
            static fn (?array $line): bool => null !== $line,
        ));

        if ([] === $lines) {
            throw ConversionNotAllowed::nothingPending();
        }

        $payload = [
            'customer_id' => $source['customer_id'],
            'pricing_mode' => $source['pricing_mode'],
            'rounding_method' => $source['rounding_method'],
            'date' => $this->clock->now()->format('Y-m-d'),
            'lines' => $lines,
        ];

        $draftId = DocumentDraftId::generate();

        $this->drafts->save(DocumentDraft::create(
            $draftId,
            $companyId,
            $command->targetDocumentType,
            $payload,
            $this->priceCalculation->calculate($payload),
            $command->actingUserId,
            $this->clock->now(),
        ));

        $this->auditLogger->log(
            'document_draft.created_as_conversion',
            'DocumentDraft',
            $draftId->toString(),
            ['source_document_id' => $command->sourceDocumentId->toString(), 'source_document_no' => $source['document_no'], 'target_document_type' => $command->targetDocumentType],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );

        return $draftId;
    }

    /**
     * @param array{line_number: int, pending_quantity: string, product_id: ?string, product_code: string, product_description: string, product_type: string, unit_code: string, quantity: string, unit_price: string, discount_percent: ?string, tax_region: string, tax_code: string, exemption_reason_code: ?string} $line
     *
     * @return array<string, mixed>|null
     */
    private static function toDraftLine(array $line, string $sourceDocumentNo): ?array
    {
        if (Quantity::fromString($line['pending_quantity'])->isZero() || Quantity::fromString($line['pending_quantity'])->isNegative()) {
            return null;
        }

        return [
            'product_id' => $line['product_id'],
            'product_code' => $line['product_code'],
            'description' => $line['product_description'],
            'product_type' => $line['product_type'],
            'unit_code' => $line['unit_code'],
            'quantity' => $line['pending_quantity'],
            'unit_price' => $line['unit_price'],
            'discounts' => null !== $line['discount_percent']
                ? [['type' => 'percentage', 'value' => $line['discount_percent']]]
                : [],
            'tax_region' => $line['tax_region'],
            'tax_code' => $line['tax_code'],
            'exemption_reason_code' => $line['exemption_reason_code'],
            'origin' => ['document_no' => $sourceDocumentNo, 'line_number' => $line['line_number']],
        ];
    }
}
