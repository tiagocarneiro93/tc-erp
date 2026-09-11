<?php

declare(strict_types=1);

namespace App\Parties\Application\Query;

final class ListSuppliers
{
    public function __construct(public readonly ?string $search)
    {
    }
}
