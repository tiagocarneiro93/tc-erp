<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Platform is a pragmatic/CRUD area (CLAUDE.md — strictness is intentional
 * in Fiscal, Tax, Inventory and Accounts), so this smoke-test handler reads
 * transaction state directly from the DBAL connection instead of through a
 * dedicated Domain port that would otherwise have a single implementation.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class PingHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(Ping $command): bool
    {
        return $this->connection->isTransactionActive();
    }
}
