<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Sealing;

use App\Output\Domain\ElectronicSealer;
use App\Shared\Domain\CompanyId;

/**
 * **Dev and test only** (`when@dev`/`services_test.yaml`; production gets
 * {@see UnconfiguredElectronicSealer}). Stands in for a trust service
 * provider so everything built on a sealed PDF — storage, once-only sealing,
 * e-mail — can be developed and tested before one is chosen.
 *
 * It is deliberately *not* a signature and says so: it appends a plainly
 * labelled marker after the PDF's `%%EOF` (trailing data a PDF reader ignores,
 * so the file still opens), carrying the SHA-256 of the PDF it was applied to.
 * Deterministic — the same PDF always gets the same marker — which is what the
 * "stored once, downloaded identically" tests lean on.
 */
final class FakeElectronicSealer implements ElectronicSealer
{
    public const MARKER = '%TC-ERP FAKE ELECTRONIC SEAL (dev/test only, not a signature)';

    public function seal(CompanyId $companyId, string $pdf): string
    {
        return $pdf."\n".self::MARKER."\n%sha256=".hash('sha256', $pdf)."\n%company=".$companyId->toString()."\n";
    }

    public static function isSealed(string $pdf): bool
    {
        return str_contains($pdf, self::MARKER);
    }
}
