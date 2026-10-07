<?php

declare(strict_types=1);

namespace App\Shared\Domain\AtIntegration;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §7.5: "After commit, a Messenger message
 * `CommunicateToAt(companyId, communicationId)` is dispatched." Issuance
 * (Fiscal) writes the outbox row inside its own transaction and then asks
 * this port to wake the consumer; the implementation lives in
 * `AtIntegration`, so Fiscal never depends on Messenger message classes
 * from another module.
 *
 * Implementations must not hand the message to the transport until the
 * surrounding transaction has committed (a worker could otherwise pick it
 * up before the row exists) — and must drop it if that transaction rolls
 * back. A lost wake-up is not a lost communication: the sweeper (§5.4) finds
 * any `pending` row regardless.
 */
interface AtCommunicationDispatcher
{
    public function dispatchAfterCommit(CompanyId $companyId, string $communicationId): void;
}
