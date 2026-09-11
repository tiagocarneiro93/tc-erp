<?php

declare(strict_types=1);

namespace App\Platform\Domain;

use App\Shared\Domain\Nif;

interface CompanyRepository
{
    public function find(CompanyId $id): ?Company;

    public function findByNif(Nif $nif): ?Company;

    public function save(Company $company): void;
}
