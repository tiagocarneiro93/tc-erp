<?php

declare(strict_types=1);

namespace App\Company\Domain;

use App\Shared\Domain\CompanyId;

interface CompanyProfileRepository
{
    public function find(CompanyId $companyId): ?CompanyProfile;

    public function save(CompanyProfile $profile): void;
}
