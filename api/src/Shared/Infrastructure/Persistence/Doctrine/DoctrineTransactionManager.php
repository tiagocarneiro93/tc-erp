<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\TransactionManager;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * `Connection::transactional()` already begins/commits/rolls back exactly
 * as needed; {@see CompanyContextConnection} runs `set_config` the moment
 * this begins the transaction, so every Domain port called from inside
 * `$operation` sees the right company's RLS scope with no extra wiring.
 */
final class DoctrineTransactionManager implements TransactionManager
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
    ) {
    }

    public function transactional(\Closure $operation): mixed
    {
        return $this->connection->transactional($operation);
    }
}
