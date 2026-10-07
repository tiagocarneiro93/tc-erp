<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Messaging;

use App\AtIntegration\Application\Command\CommunicateToAt;
use App\Shared\Domain\AtIntegration\AtCommunicationDispatcher;
use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Messenger\CompanyStamp;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Sends through `command.bus` on purpose, with the bus name pre-set to
 * `at.bus`: issuance runs *inside* `command.bus`'s root dispatch, and only
 * that bus's `dispatch_after_current_bus` middleware can hold a message back
 * until the handler — `doctrine_transaction` included — has finished and
 * committed. (A message sent to `at.bus` from inside `command.bus` would go
 * straight out, ahead of the commit.) The {@see BusNameStamp} makes the
 * worker pick the message up on `at.bus` regardless of which bus sent it.
 */
final class MessengerAtCommunicationDispatcher implements AtCommunicationDispatcher
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function dispatchAfterCommit(CompanyId $companyId, string $communicationId): void
    {
        $this->commandBus->dispatch(new CommunicateToAt($companyId->toString(), $communicationId), [
            new CompanyStamp($companyId),
            new BusNameStamp('at.bus'),
            new DispatchAfterCurrentBusStamp(),
        ]);
    }
}
