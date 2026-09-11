<?php

declare(strict_types=1);

namespace App\Parties\Application\Query;

use App\Parties\Domain\SupplierId;

final class GetSupplier
{
    public function __construct(public readonly SupplierId $supplierId)
    {
    }
}
