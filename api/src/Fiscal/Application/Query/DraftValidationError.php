<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

/**
 * docs/plans/phase-2.md task 2.4: "validation errors are per-field" —
 * every rule reports which field it's about, so the web app (and task
 * 2.6's issuance use case) can point at exactly what's wrong rather than
 * a single opaque failure.
 */
final class DraftValidationError
{
    public function __construct(
        public readonly string $field,
        public readonly string $message,
    ) {
    }
}
