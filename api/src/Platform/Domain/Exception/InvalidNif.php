<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

final class InvalidNif extends \DomainException
{
    public function __construct()
    {
        parent::__construct('This is not a valid NIF.');
    }
}
