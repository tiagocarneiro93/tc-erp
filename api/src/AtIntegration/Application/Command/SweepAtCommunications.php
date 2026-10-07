<?php

declare(strict_types=1);

namespace App\AtIntegration\Application\Command;

/**
 * technical-scope.md §5.4/§7.5: the scheduled safety net. Wakes every outbox
 * row that is due, whichever company it belongs to.
 */
final class SweepAtCommunications
{
    public function __construct(public readonly int $limit = 200)
    {
    }
}
