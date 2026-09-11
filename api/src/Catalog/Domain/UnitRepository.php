<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

interface UnitRepository
{
    /**
     * @return list<Unit>
     */
    public function findAll(): array;
}
