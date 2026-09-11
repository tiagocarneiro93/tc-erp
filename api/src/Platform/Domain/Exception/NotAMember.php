<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

final class NotAMember extends \DomainException
{
    public function __construct()
    {
        parent::__construct('This user is not a member of this company.');
    }
}
