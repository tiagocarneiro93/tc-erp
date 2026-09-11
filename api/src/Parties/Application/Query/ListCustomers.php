<?php

declare(strict_types=1);

namespace App\Parties\Application\Query;

/**
 * Cursor/limit windowing happens in the controller, over this handler's
 * full (DB-filtered) result — same split as `CompaniesController::listMine()`
 * (task 0.10)'s use of `CursorPaginator`.
 */
final class ListCustomers
{
    public function __construct(public readonly ?string $search)
    {
    }
}
