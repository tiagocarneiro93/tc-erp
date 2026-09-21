<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

/**
 * technical-scope.md §7.6: `draft` → `active` (once a validation code
 * exists) → `finished`/`cancelled` — the latter two both reachable only
 * from `active`, per docs/plans/phase-2.md task 2.2's lifecycle bullet
 * ("draft → active ... → finished/cancelled"). Both are terminal.
 */
enum SeriesStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Finished = 'finished';
    case Cancelled = 'cancelled';
}
