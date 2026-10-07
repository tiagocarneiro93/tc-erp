<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

/**
 * One stored `document_lines` row. Amounts are the stored decimal strings;
 * how they map onto SAF-T's `CreditAmount`/`UnitPrice`/`SettlementAmount` is
 * the writer's job (see {@see \App\Fiscal\Infrastructure\Saft\SaftXmlWriter}).
 */
final class SaftDocumentLine
{
    /**
     * @param list<string> $originDocumentNos `OrderReferences/OriginatingON`
     */
    public function __construct(
        public readonly int $lineNumber,
        public readonly string $productCode,
        public readonly string $productDescription,
        public readonly string $quantity,
        public readonly string $unitCode,
        public readonly string $unitPrice,
        public readonly string $discountAmount,
        public readonly string $settlementAmount,
        public readonly string $netAmount,
        public readonly string $taxRegion,
        public readonly string $taxCode,
        public readonly string $taxPercentage,
        public readonly ?string $exemptionReasonCode,
        public readonly ?string $exemptionReasonText,
        public readonly \DateTimeImmutable $taxPointDate,
        public readonly array $originDocumentNos,
    ) {
    }
}
