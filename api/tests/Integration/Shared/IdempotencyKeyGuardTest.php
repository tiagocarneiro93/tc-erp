<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Idempotency\IdempotencyKeyMismatch;
use App\Shared\Domain\Idempotency\MissingIdempotencyKey;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use App\Shared\Infrastructure\Http\IdempotencyKeyGuard;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * CLAUDE.md task 0.11 acceptance: same key + same payload returns the
 * stored response without re-running the handler; same key + different
 * payload is rejected.
 */
final class IdempotencyKeyGuardTest extends KernelTestCase
{
    public function testTheSameKeyAndPayloadReturnsTheStoredResponseWithoutRerunningTheHandler(): void
    {
        self::bootKernel();
        [$guard, $connection, $context] = $this->services();
        $companyId = CompanyId::generate();
        $context->set($companyId);
        $connection->beginTransaction();

        try {
            $request = Request::create('/api/v1/companies/x/documents/drafts/y/issue', 'POST', content: '{"a":1}');
            $request->headers->set('Idempotency-Key', 'key-1');

            $calls = 0;
            $handle = static function () use (&$calls): JsonResponse {
                ++$calls;

                return new JsonResponse(['id' => 'doc-1'], 201);
            };

            $first = $guard->guard($request, $companyId, $handle);
            self::assertSame(201, $first->getStatusCode());
            self::assertSame(1, $calls);

            $second = $guard->guard($request, $companyId, $handle);
            self::assertSame(201, $second->getStatusCode());
            self::assertSame('{"id":"doc-1"}', $second->getContent());
            self::assertSame(1, $calls, 'The handler must not run again for a replayed key+payload.');
        } finally {
            $connection->rollBack();
            $context->clear();
        }
    }

    public function testTheSameKeyWithADifferentPayloadIsRejected(): void
    {
        self::bootKernel();
        [$guard, $connection, $context] = $this->services();
        $companyId = CompanyId::generate();
        $context->set($companyId);
        $connection->beginTransaction();

        try {
            $first = Request::create('/api/v1/companies/x/documents/drafts/y/issue', 'POST', content: '{"a":1}');
            $first->headers->set('Idempotency-Key', 'key-2');
            $guard->guard($first, $companyId, static fn (): JsonResponse => new JsonResponse(['id' => 'doc-2'], 201));

            $second = Request::create('/api/v1/companies/x/documents/drafts/y/issue', 'POST', content: '{"a":2}');
            $second->headers->set('Idempotency-Key', 'key-2');

            $this->expectException(IdempotencyKeyMismatch::class);
            $guard->guard($second, $companyId, static fn (): JsonResponse => new JsonResponse(['id' => 'doc-3'], 201));
        } finally {
            $connection->rollBack();
            $context->clear();
        }
    }

    public function testAMissingIdempotencyKeyHeaderIsRejected(): void
    {
        self::bootKernel();
        [$guard, $connection, $context] = $this->services();
        $companyId = CompanyId::generate();
        $context->set($companyId);
        $connection->beginTransaction();

        try {
            $this->expectException(MissingIdempotencyKey::class);
            $guard->guard(Request::create('/whatever', 'POST', content: '{}'), $companyId, static fn (): JsonResponse => new JsonResponse());
        } finally {
            $connection->rollBack();
            $context->clear();
        }
    }

    /**
     * @return array{0: IdempotencyKeyGuard, 1: Connection, 2: RequestCompanyContext}
     */
    private function services(): array
    {
        $container = self::getContainer();

        /** @var IdempotencyKeyGuard $guard */
        $guard = $container->get(IdempotencyKeyGuard::class);
        /** @var Connection $connection */
        $connection = $container->get('doctrine.dbal.default_connection');
        /** @var RequestCompanyContext $context */
        $context = $container->get(RequestCompanyContext::class);

        return [$guard, $connection, $context];
    }
}
