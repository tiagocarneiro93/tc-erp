<?php

declare(strict_types=1);

namespace App\Shared\Domain\AtIntegration;

/**
 * One issued `document_lines` row, reduced to what the e-Fatura
 * `LineSummary` structure (`at-ws-efatura-aspetos-especificos.pdf` §2.1.1.1
 * item 1.6.14) groups by. Amounts are decimal strings (CLAUDE.md: never
 * float); the grouping and summing happen in `AtIntegration`, not here.
 */
final class AtCommunicableLine
{
    /**
     * @param list<string> $originDocumentNos `OriginatingON` values — the `document_no` of every document this line was converted from (task 2.8)
     */
    public function __construct(
        public readonly string $taxRegion,
        public readonly string $taxCode,
        public readonly string $taxPercentage,
        public readonly ?string $exemptionReasonCode,
        public readonly string $netAmount,
        public readonly \DateTimeImmutable $taxPointDate,
        public readonly array $originDocumentNos,
    ) {
    }
}
