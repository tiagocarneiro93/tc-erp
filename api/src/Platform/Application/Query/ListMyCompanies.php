<?php

declare(strict_types=1);

namespace App\Platform\Application\Query;

use App\Platform\Domain\UserId;

final class ListMyCompanies
{
    public function __construct(public readonly UserId $userId)
    {
    }
}
