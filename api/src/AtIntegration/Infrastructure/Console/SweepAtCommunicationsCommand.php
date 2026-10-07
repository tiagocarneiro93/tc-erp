<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Console;

use App\AtIntegration\Application\Command\SweepAtCommunications;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * technical-scope.md §5.4/§7.5: run this every minute (docker-compose's
 * `at-sweeper` service; any scheduler works). It only lists what is due and
 * queues it — the `messenger:consume async` worker does the actual sending.
 */
#[AsCommand(name: 'app:at:sweep', description: 'Queue every AT communication that is due for (re)sending.')]
final class SweepAtCommunicationsCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'at.bus')]
        private readonly MessageBusInterface $atBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $envelope = $this->atBus->dispatch(new SweepAtCommunications());
        $woken = $envelope->last(HandledStamp::class)?->getResult();

        $output->writeln(\sprintf('Woke %d AT communication(s).', \is_int($woken) ? $woken : 0), OutputInterface::VERBOSITY_VERBOSE);

        return Command::SUCCESS;
    }
}
