<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * docs/plans/phase-2.md task 2.9: a receipt allocates its total across
 * one or more open invoices — never more than an invoice's open amount,
 * never against a cancelled document, and never against a document type
 * that doesn't create a receivable in the first place (`account_effect`
 * other than `debit` — e.g. NC, or FR which settles at issuance).
 */
final class ReceiptAllocationNotAllowed extends \DomainException implements ProblemDetails
{
    public static function noAllocations(): self
    {
        return new self('A receipt must allocate to at least one document.');
    }

    public static function documentIsCancelled(int $index): self
    {
        return new self(\sprintf('Allocation #%d: cannot allocate against a cancelled document.', $index + 1));
    }

    public static function documentDoesNotCreateAReceivable(int $index, string $documentType): self
    {
        return new self(\sprintf('Allocation #%d: a %s does not create an open receivable to settle.', $index + 1, $documentType));
    }

    public static function exceedsOpenAmount(int $index): self
    {
        return new self(\sprintf('Allocation #%d: the amount exceeds the document\'s open balance.', $index + 1));
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function problemType(): string
    {
        return 'receipt-allocation-not-allowed';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
