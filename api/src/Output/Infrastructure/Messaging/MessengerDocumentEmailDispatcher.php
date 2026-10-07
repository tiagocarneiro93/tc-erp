<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Messaging;

use App\Output\Application\SendDocumentEmail;
use App\Output\Domain\DocumentEmailDispatcher;
use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Messenger\CompanyStamp;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Same arrangement as the AT wake-up: sent through `command.bus` so that
 * `dispatch_after_current_bus` holds it back until the request's transaction
 * has committed, with the bus name pre-set so the worker handles it on
 * `output.bus`.
 */
final class MessengerDocumentEmailDispatcher implements DocumentEmailDispatcher
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function dispatchAfterCommit(CompanyId $companyId, string $documentId, array $recipients, ?string $message, string $actingUserId, string $ip, string $userAgent): void
    {
        $this->commandBus->dispatch(new SendDocumentEmail($companyId->toString(), $documentId, $recipients, $message, $actingUserId, $ip, $userAgent), [
            new CompanyStamp($companyId),
            new BusNameStamp('output.bus'),
            new DispatchAfterCurrentBusStamp(),
        ]);
    }
}
