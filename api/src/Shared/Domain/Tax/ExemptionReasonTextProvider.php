<?php

declare(strict_types=1);

namespace App\Shared\Domain\Tax;

/**
 * Cross-module port (docs/decisions/0004's pattern): issuance freezes the
 * exemption wording into `document_lines.exemption_reason_text`
 * (technical-scope.md §6.6) — the "Menção que consta da fatura" of AT's own
 * table (`docs/legal/at-tabela-codigos-motivo-isencao.pdf`), which the printed
 * document and SAF-T's `TaxExemptionReason` both need and which must not
 * change retroactively if the reference table is ever updated.
 */
interface ExemptionReasonTextProvider
{
    /**
     * @return string|null null when the code is unknown
     */
    public function wordingFor(string $code): ?string;
}
