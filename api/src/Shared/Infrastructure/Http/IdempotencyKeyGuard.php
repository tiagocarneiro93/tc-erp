<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Idempotency\IdempotencyKeyMismatch;
use App\Shared\Domain\Idempotency\IdempotencyKeyStore;
use App\Shared\Domain\Idempotency\MissingIdempotencyKey;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps an issuing/communicating endpoint's handling with `Idempotency-Key`
 * support (technical-scope.md §9.1), ready for Phase 2 to use: a controller
 * calls {@see self::guard()} around the code that would otherwise run twice
 * on a client retry.
 */
final class IdempotencyKeyGuard
{
    public function __construct(private readonly IdempotencyKeyStore $store)
    {
    }

    /**
     * @param \Closure(): Response $handle
     */
    public function guard(Request $request, CompanyId $companyId, \Closure $handle): Response
    {
        $key = $request->headers->get('Idempotency-Key');

        if (null === $key) {
            throw new MissingIdempotencyKey();
        }

        $requestHash = hash('sha256', $request->getContent());
        $stored = $this->store->find($companyId, $key);

        if (null !== $stored) {
            if ($stored->requestHash !== $requestHash) {
                throw new IdempotencyKeyMismatch();
            }

            return new Response($stored->body, $stored->status, ['Content-Type' => 'application/json']);
        }

        $response = $handle();
        $this->store->store($companyId, $key, $requestHash, $response->getStatusCode(), (string) $response->getContent());

        return $response;
    }
}
