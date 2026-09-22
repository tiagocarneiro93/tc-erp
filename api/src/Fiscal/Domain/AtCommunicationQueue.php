<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.12/§7.5: the AT outbox. §7.1 step 12 writes a
 * `pending` row in the same transaction as the document itself, so the
 * actual communication (Phase 3: a dispatched Messenger message, retried
 * by a scheduled sweeper) always has something to pick up — nothing is
 * ever lost even if the process crashes right after commit. Not locked
 * down like the fiscal-document family (task 2.3): retries mutate
 * `status`/`attempts`/`next_attempt_at` freely, which is why `at_communications`
 * is deliberately absent from §6.9's insert-only table list.
 */
interface AtCommunicationQueue
{
    public function enqueue(
        CompanyId $companyId,
        string $kind,
        string $subjectType,
        string $subjectId,
        \DateTimeImmutable $now,
    ): void;

    /**
     * docs/plans/phase-3.md task 3.1/decision 3: series register/finish/cancel
     * are synchronous — by the time this is called, AT has already answered,
     * so the row is written with its outcome already known rather than
     * `pending` for a sweeper to find. `$kind` extends §6.12's own
     * illustrative `series_register|series_finish|invoice|transport` list
     * with `series_cancel`, for the same audit-trail reason `series_finish`
     * is there — that list reads as incomplete, not a fixed enum (the
     * column itself is a plain string, no DB-level CHECK constraint).
     */
    public function recordResolved(
        CompanyId $companyId,
        string $kind,
        string $subjectType,
        string $subjectId,
        string $status,
        int $responseCode,
        string $responseMessage,
        ?string $atReference,
        \DateTimeImmutable $now,
    ): void;
}
