<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\DocumentDraft;
use App\Fiscal\Domain\DocumentDraftRepository;
use App\Fiscal\Domain\Exception\DocumentDraftNotFound;
use App\Fiscal\Domain\SeriesId;
use App\Fiscal\Domain\SeriesRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\CustomerExistenceChecker;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * docs/plans/phase-2.md task 2.4's validation bullet, reused as-is by
 * task 2.6's issuance use case before it lets a draft become a document:
 * customer resolvable, at least one line, exemption reason present on any
 * 0%-rate line, references present for NC/ND, series matches the draft's
 * document type. Not yet covered here (no rule given for it): whether a
 * customer is *required* at all for a given document type — only that
 * *if* one is set, it must resolve.
 */
#[AsMessageHandler(bus: 'query.bus')]
final class ValidateDraftHandler
{
    private const TYPES_REQUIRING_REFERENCES = ['NC', 'ND'];

    public function __construct(
        private readonly DocumentDraftRepository $drafts,
        private readonly SeriesRepository $series,
        private readonly CustomerExistenceChecker $customers,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<DraftValidationError>
     */
    public function __invoke(ValidateDraft $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.issue', $companyId)) {
            throw new PermissionDenied();
        }

        $draft = $this->drafts->find($companyId, $query->draftId);

        if (null === $draft) {
            throw new DocumentDraftNotFound();
        }

        return [
            ...$this->validateLines($draft),
            ...$this->validateCustomer($companyId, $draft),
            ...$this->validateReferences($draft),
            ...$this->validateSeries($companyId, $draft),
        ];
    }

    /**
     * @return list<DraftValidationError>
     */
    private function validateLines(DocumentDraft $draft): array
    {
        $lines = $draft->payload()['lines'] ?? null;

        if (!\is_array($lines) || [] === $lines) {
            return [new DraftValidationError('lines', 'At least one line is required.')];
        }

        $calculated = $draft->calculated();

        if (null === $calculated) {
            return [new DraftValidationError('payload', 'The draft could not be calculated; check the line data.')];
        }

        $errors = [];
        /** @var array<int, array{tax_percentage: string, exemption_reason_code: ?string}> $calculatedLines */
        $calculatedLines = $calculated['lines'];
        foreach ($calculatedLines as $index => $line) {
            if ('0.00' === $line['tax_percentage'] && null === $line['exemption_reason_code']) {
                $errors[] = new DraftValidationError(\sprintf('lines[%d].exemption_reason_code', $index), 'An exemption reason is required when the tax rate is 0%.');
            }
        }

        return $errors;
    }

    /**
     * @return list<DraftValidationError>
     */
    private function validateCustomer(CompanyId $companyId, DocumentDraft $draft): array
    {
        $customerId = $draft->customerId();

        if (null !== $customerId && !$this->customers->exists($companyId, $customerId)) {
            return [new DraftValidationError('customer_id', 'This customer does not exist.')];
        }

        return [];
    }

    /**
     * @return list<DraftValidationError>
     */
    private function validateReferences(DocumentDraft $draft): array
    {
        if (!\in_array($draft->documentType(), self::TYPES_REQUIRING_REFERENCES, true)) {
            return [];
        }

        $references = $draft->payload()['references'] ?? null;

        if (!\is_array($references) || [] === $references) {
            return [new DraftValidationError('references', 'At least one reference to the original document is required for credit/debit notes.')];
        }

        return [];
    }

    /**
     * @return list<DraftValidationError>
     */
    private function validateSeries(CompanyId $companyId, DocumentDraft $draft): array
    {
        $seriesId = $draft->seriesId();

        if (null === $seriesId) {
            return [];
        }

        try {
            $series = $this->series->find($companyId, SeriesId::fromString($seriesId));
        } catch (\InvalidArgumentException) {
            return [new DraftValidationError('series_id', 'This series does not exist.')];
        }

        if (null === $series) {
            return [new DraftValidationError('series_id', 'This series does not exist.')];
        }

        if ($series->documentType() !== $draft->documentType()) {
            return [new DraftValidationError('series_id', \sprintf('This series is for document type "%s", not "%s".', $series->documentType(), $draft->documentType()))];
        }

        return [];
    }
}
