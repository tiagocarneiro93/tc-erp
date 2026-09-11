<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(env: 'REDIS_URL')]
        private readonly string $redisUrl,
    ) {
    }

    #[Route('/api/v1/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $healthy = !\in_array(false, $checks, true);

        return new JsonResponse(
            ['status' => $healthy ? 'ok' : 'error', 'checks' => $checks],
            $healthy ? 200 : 503,
        );
    }

    private function checkDatabase(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (DbalException) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            $redis = new \Redis();
            $host = parse_url($this->redisUrl, PHP_URL_HOST) ?: '127.0.0.1';
            $port = parse_url($this->redisUrl, PHP_URL_PORT) ?: 6379;
            $redis->connect($host, $port, 1.0);

            return $redis->ping();
        } catch (\RedisException) {
            return false;
        }
    }
}
