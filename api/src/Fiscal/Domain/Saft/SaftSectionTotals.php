<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

/**
 * `NumberOfEntries`/`TotalDebit`/`TotalCredit` of one `SourceDocuments`
 * section. Confirmed from AT's own `saft-pt-sample-instance.xml`: every
 * document is counted, but cancelled ones (status `A`) are left out of the
 * debit/credit totals — the sample's `SalesInvoices` header (24 entries,
 * `TotalCredit` 17894.049) equals the credit lines of the 18 `N` and the 1 `R`
 * document, with the 5 `A` documents counted but not summed.
 */
final class SaftSectionTotals
{
    public function __construct(
        public readonly int $entries,
        public readonly string $totalDebit,
        public readonly string $totalCredit,
    ) {
    }
}
