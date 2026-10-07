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
    /**
     * Written on the caller's own (default) connection, inside the caller's
     * transaction — the whole point of the outbox pattern: if issuance
     * rolls back, so does the row; if it commits, the row is there.
     *
     * @return string the new row's id, for {@see \App\Shared\Domain\AtIntegration\AtCommunicationDispatcher}
     */
    public function enqueue(
        CompanyId $companyId,
        string $kind,
        string $subjectType,
        string $subjectId,
        \DateTimeImmutable $now,
    ): string;

    /**
     * docs/plans/phase-3.md task 3.2: a document's status changed *after*
     * AT already accepted its registration (today: an `OR`/`PF`/`NE`
     * reaching `F` once fully converted, task 2.8 — `ChangeWorkStatus`,
     * `at-ws-efatura-aspetos-especificos.pdf` §2.1.5). Only enqueues when an
     * `accepted` registration exists: any other registration (not sent yet,
     * failed, in flight) reads the document's current status when it is
     * actually sent, so AT hears about the new status in the registration
     * itself. Cancellation never needs this — task 2.10 only allows it
     * before AT confirmed the document (`pending`/`failed`/`rejected`).
     *
     * @return string|null the new row's id, or null when nothing was enqueued
     */
    public function enqueueStatusChange(CompanyId $companyId, string $subjectId, \DateTimeImmutable $now): ?string;

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
