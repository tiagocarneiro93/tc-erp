<?php

declare(strict_types=1);

namespace App\Company\Domain;

use App\Shared\Domain\CompanyId;

interface CompanySettingRepository
{
    public function find(CompanyId $companyId, string $key): ?CompanySetting;

    public function save(CompanySetting $setting): void;
}
