<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Signing;

use App\Shared\Domain\Decimal\Money;

/**
 * Portaria 363/2010, Art. 6.º §1: the exact message a document's hash
 * signs — `InvoiceDate;SystemEntryDate;InvoiceNo;GrossTotal;PreviousHash`,
 * `;`-joined. Field formats confirmed from Despacho 8632/2014 §4.4–4.5's
 * own worked example (`docs/legal/despacho-8632-2014.pdf`), no longer
 * `[VERIFY]`:
 *  - InvoiceDate: `AAAA-MM-DD`.
 *  - SystemEntryDate: `AAAA-MM-DDTHH:MM:SS` — no timezone offset, no
 *    fractional seconds, despite every timestamp elsewhere in this system
 *    being `TIMESTAMPTZ` in UTC (technical-scope.md's architecture rule);
 *    this one field's wire format is fixed by the Despacho itself.
 *  - InvoiceNo: exactly `Document::documentNo()` (e.g. `"FT 2026A/15"`),
 *    already in the `{code} {series}/{number}` shape the Despacho
 *    requires — built once at issuance (task 2.6), not reconstructed here.
 *  - GrossTotal: `Money::toString()` — 2 decimals, `.` separator, no
 *    thousands separator — matches exactly.
 *  - PreviousHash: empty string for a series' first document (Despacho
 *    §2.1.5), otherwise the prior document's `Hash`, unchanged.
 */
final class SigningMessage
{
    private function __construct()
    {
    }

    public static function build(
        \DateTimeImmutable $invoiceDate,
        \DateTimeImmutable $systemEntryDate,
        string $invoiceNo,
        Money $grossTotal,
        ?string $previousHash,
    ): string {
        return implode(';', [
            $invoiceDate->format('Y-m-d'),
            $systemEntryDate->format('Y-m-d\TH:i:s'),
            $invoiceNo,
            $grossTotal->toString(),
            $previousHash ?? '',
        ]);
    }
}
