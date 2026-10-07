<?php

declare(strict_types=1);

namespace App\AtIntegration\Domain\Outbox;

use App\Shared\Domain\CompanyId;

/**
 * The consuming side of the AT outbox (`at_communications`, technical-scope.md
 * §6.12/§7.5). Fiscal writes rows (`Fiscal\Domain\AtCommunicationQueue`);
 * this module claims them, sends them and records what AT answered.
 *
 * Every method except {@see self::findDue()} runs inside a company
 * transaction (RLS scoped); `findDue()` is the one cross-company call and
 * goes through a `SECURITY DEFINER` function that returns identifiers only.
 */
interface AtCommunicationOutbox
{
    /**
     * Atomically moves a `pending`/`failed` row — or a `sending` one whose
     * lease expired, i.e. its worker died — to `sending`, counts the attempt
     * and pushes `next_attempt_at` out to the lease deadline. Returns null
     * when there is nothing to claim (already processed, being processed by
     * another worker, or not due yet), which is how duplicate wake-ups from
     * issuance and the sweeper stay harmless.
     */
    public function claim(CompanyId $companyId, string $communicationId, \DateTimeImmutable $now): ?ClaimedAtCommunication;

    /**
     * @param string                  $status        accepted|rejected|failed
     * @param \DateTimeImmutable|null $nextAttemptAt only for `failed`
     */
    public function recordOutcome(
        CompanyId $companyId,
        string $communicationId,
        string $status,
        ?int $responseCode,
        string $responseMessage,
        ?string $requestDigest,
        ?\DateTimeImmutable $nextAttemptAt,
        \DateTimeImmutable $now,
    ): void;

    /**
     * §7.5 sweeper: `pending`/`failed` rows past `next_attempt_at`, and
     * `sending` rows whose lease ran out, that still have automatic attempts
     * left.
     *
     * @return list<DueAtCommunication>
     */
    public function findDue(\DateTimeImmutable $now, int $limit): array;

    /**
     * Manual retry (docs/plans/phase-3.md task 3.8's "retry" action): puts
     * every `failed`/`rejected` row of a document back to `pending` with a
     * fresh attempt budget. Returns the ids to wake.
     *
     * @return list<string>
     */
    public function requestRetry(CompanyId $companyId, string $subjectType, string $subjectId, \DateTimeImmutable $now): array;
}
