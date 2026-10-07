<?php

declare(strict_types=1);

namespace App\AtIntegration\Application\Command;

use App\AtIntegration\Domain\Outbox\AtCommunicationOutbox;
use App\Shared\Domain\AtIntegration\AtCommunicationDispatcher;
use App\Shared\Domain\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Lists due work through the `SECURITY DEFINER` function (identifiers only,
 * §5.4) and hands each item to the same {@see CommunicateToAtHandler} path a
 * freshly issued document takes, now in its own company's context. Wake-ups
 * are idempotent — claiming is atomic — so overlapping sweeps, or a sweep
 * racing the post-commit dispatch, never double-send.
 */
#[AsMessageHandler(bus: 'at.bus')]
final class SweepAtCommunicationsHandler
{
    public function __construct(
        private readonly AtCommunicationOutbox $outbox,
        private readonly AtCommunicationDispatcher $dispatcher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return int how many rows were woken
     */
    public function __invoke(SweepAtCommunications $command): int
    {
        $due = $this->outbox->findDue($this->clock->now(), $command->limit);

        foreach ($due as $item) {
            $this->dispatcher->dispatchAfterCommit($item->companyId, $item->communicationId);
        }

        return \count($due);
    }
}
