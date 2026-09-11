<?php

declare(strict_types=1);

namespace App\Shared\Domain;

interface CountryRepository
{
    /**
     * @return list<Country>
     */
    public function findAll(): array;
}
