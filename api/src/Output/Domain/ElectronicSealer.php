<?php

declare(strict_types=1);

namespace App\Output\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §7.8: from 1 January 2027 a PDF sent electronically must
 * carry a qualified electronic seal of the issuing company (DL 28/2019 Art.
 * 12.º, eIDAS). The keys live in a qualified device at a trust service
 * provider and cannot be exported, so sealing is **remote**: the platform
 * renders the PDF, hashes it, the provider signs the hash, and the signature
 * is embedded in the PDF (PAdES, ETSI EN 319 142). The PDF itself never leaves
 * the platform.
 *
 * A sealed PDF is a signed artifact — it must be produced once and kept
 * ({@see \App\Output\Application\SealedDocumentPdfs}), never re-sealed per
 * request, since every seal costs money and carries its own timestamp.
 *
 * Implementations: {@see \App\Output\Infrastructure\Sealing\FakeElectronicSealer}
 * (dev/test only), {@see \App\Output\Infrastructure\Sealing\UnconfiguredElectronicSealer}
 * (the default everywhere else — it refuses, loudly) and, once a provider is
 * chosen (§14.3 item 15), a real adapter. Still open before that adapter can be
 * written, and not resolvable from a document: whether one TCWeb certificate may
 * seal on behalf of client companies, or each company needs its own (§7.8
 * assumes the latter).
 */
interface ElectronicSealer
{
    /**
     * @param string $pdf the exact PDF bytes to seal
     *
     * @return string the sealed PDF
     *
     * @throws SealingFailed when no seal could be applied — never returns an unsealed file
     */
    public function seal(CompanyId $companyId, string $pdf): string;
}
