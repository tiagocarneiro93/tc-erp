<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

use App\Shared\Domain\Company\CompanyFiscalIdentity;
use App\Shared\Domain\CompanyId;

/**
 * Writes one SAF-T (PT) file, streaming, to `$targetPath`.
 */
interface SaftFileGenerator
{
    public function generate(
        CompanyId $companyId,
        CompanyFiscalIdentity $company,
        SaftExportPeriod $period,
        \DateTimeImmutable $createdAt,
        string $targetPath,
    ): SaftGenerationSummary;
}
