<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

final class UnknownRole extends \DomainException
{
    public function __construct()
    {
        parent::__construct('This is not a known role.');
    }
}
