<?php

declare(strict_types=1);

namespace App\Platform\Application\Security;

use App\Platform\Domain\UserId;

/**
 * Lets UI/Http controllers ask "who is making this request" without
 * depending on the Symfony Security adapter directly (Deptrac: UI/Http may
 * depend on Application, never on another tier's Infrastructure).
 */
interface CurrentUserId
{
    public function id(): UserId;
}
