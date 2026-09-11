<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

final class InvalidCurrentPassword extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The current password is incorrect.');
    }
}
