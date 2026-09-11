<?php

declare(strict_types=1);

namespace App\Platform\Application\Query;

final class MyCompanyView
{
    public function __construct(
        public readonly string $companyId,
        public readonly string $nif,
        public readonly string $legalName,
        public readonly string $role,
    ) {
    }
}
