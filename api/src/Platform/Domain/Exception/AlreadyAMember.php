<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

final class AlreadyAMember extends \DomainException
{
    public function __construct()
    {
        parent::__construct('This user is already a member of this company.');
    }
}
