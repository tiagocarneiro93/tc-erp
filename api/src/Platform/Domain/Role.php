<?php

declare(strict_types=1);

namespace App\Platform\Domain;

/**
 * Global reference data (technical-scope.md §6.1), seeded by a migration —
 * see docs/decisions/0002-roles-and-permissions.md for the permission list.
 */
final class Role
{
    public function __construct(
        private readonly string $code,
        private readonly string $name,
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }
}
