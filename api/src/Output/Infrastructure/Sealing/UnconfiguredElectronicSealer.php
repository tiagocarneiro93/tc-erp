<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Sealing;

use App\Output\Domain\ElectronicSealer;
use App\Output\Domain\SealingFailed;
use App\Shared\Domain\CompanyId;

/**
 * The default outside dev/test: until a qualified trust service provider is
 * integrated there is no way to seal, and sending an unsealed PDF as if it were
 * fine would be worse than failing — so it fails, with a message that says what
 * is missing.
 */
final class UnconfiguredElectronicSealer implements ElectronicSealer
{
    public function seal(CompanyId $companyId, string $pdf): string
    {
        throw new SealingFailed('No electronic seal provider is configured: PDFs cannot be sealed for electronic sending yet (technical-scope.md §14.3 item 15).');
    }
}
