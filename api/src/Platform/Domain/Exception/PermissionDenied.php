<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

final class PermissionDenied extends \DomainException
{
    public function __construct()
    {
        parent::__construct('You do not have permission to do this.');
    }
}
