<?php

declare(strict_types=1);

namespace App\Parties\Domain;

interface AddressRepository
{
    public function save(Address $address): void;
}
