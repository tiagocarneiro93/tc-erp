<?php

declare(strict_types=1);

namespace App\Shared\Domain\Security;

/**
 * Cross-module twin of `Platform\Application\Security\CurrentUserId`
 * (docs/decisions/0004), for controllers outside Platform that need the
 * acting user's id to build an outgoing command — as a plain string, not
 * Platform's `UserId` value object, matching how `AuditLogger::log()`
 * already accepts `?string $actingUserId`.
 */
interface CurrentActorId
{
    public function id(): string;
}
