<?php

declare(strict_types=1);

namespace App\Shared\Domain\Fiscal;

/**
 * One stored `document_lines` row, as printed. Amounts are the stored decimal
 * strings — formatting for the page is the template's job, arithmetic is
 * nobody's (CLAUDE.md: only `PriceCalculator` computes).
 */
final class PrintableLine
{
    /**
     * @param list<string> $originDocumentNos
     */
    public function __construct(
        public readonly int $lineNumber,
        public readonly string $productCode,
        public readonly string $description,
        public readonly string $quantity,
        public readonly string $unitCode,
        public readonly string $unitPrice,
        public readonly ?string $discountPercent,
        public readonly string $netAmount,
        public readonly string $taxPercentage,
        public readonly string $taxCode,
        public readonly ?string $exemptionCode,
        public readonly ?string $exemptionText,
        public readonly array $originDocumentNos,
    ) {
    }
}
