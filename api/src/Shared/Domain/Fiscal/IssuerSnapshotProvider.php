<?php

declare(strict_types=1);

namespace App\Shared\Domain\Fiscal;

use App\Shared\Domain\CompanyId;

/**
 * Same reasoning as {@see CustomerSnapshotProvider}, for the issuing
 * company's own data frozen into `documents.issuer_snapshot`.
 */
interface IssuerSnapshotProvider
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(CompanyId $companyId): array;
}
