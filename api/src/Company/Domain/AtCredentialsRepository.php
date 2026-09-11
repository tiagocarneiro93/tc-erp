<?php

declare(strict_types=1);

namespace App\Company\Domain;

use App\Shared\Domain\CompanyId;

interface AtCredentialsRepository
{
    public function find(CompanyId $companyId): ?AtCredentials;

    public function save(AtCredentials $credentials): void;
}
