<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

final class CompanyAlreadyExists extends \DomainException
{
    public function __construct()
    {
        parent::__construct('A company with this NIF already exists.');
    }
}
