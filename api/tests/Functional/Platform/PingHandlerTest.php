<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform;

use App\Platform\Application\Command\Ping;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class PingHandlerTest extends KernelTestCase
{
    use HandleTrait;

    public function testPingRunsInsideATransaction(): void
    {
        self::bootKernel();

        /** @var MessageBusInterface $commandBus */
        $commandBus = self::getContainer()->get('command.bus');
        $this->messageBus = $commandBus;

        $result = $this->handle(new Ping());

        self::assertTrue($result, 'command.bus must wrap handlers in a transaction (doctrine_transaction middleware)');
    }

    public function testEnvelopeCarriesAHandledStamp(): void
    {
        self::bootKernel();

        /** @var MessageBusInterface $commandBus */
        $commandBus = self::getContainer()->get('command.bus');

        $envelope = $commandBus->dispatch(new Ping());

        self::assertNotNull($envelope->last(HandledStamp::class));
        self::assertInstanceOf(Envelope::class, $envelope);
    }
}
