<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CSRF protection for the cookie-authenticated API (CLAUDE.md task 0.8):
 * every state-changing /api/v1 request must carry this header. A same-origin
 * XHR/fetch can set it freely; a cross-site <form> POST (the classic CSRF
 * vector) cannot, and a cross-origin script attempting to set it would
 * trigger a CORS preflight this API doesn't approve. Paired with the
 * SameSite=Lax session cookie (config/packages/framework.yaml).
 */
final class CsrfHeaderListener implements EventSubscriberInterface
{
    private const HEADER = 'X-Requested-With';
    private const EXPECTED_VALUE = 'XMLHttpRequest';
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->needsProtection($request)) {
            return;
        }

        if (self::EXPECTED_VALUE !== $request->headers->get(self::HEADER)) {
            $event->setResponse(new JsonResponse(
                [
                    'type' => 'https://tc-erp.example/problems/missing-csrf-header',
                    'title' => 'Missing or invalid '.self::HEADER.' header.',
                    'status' => 403,
                ],
                403,
                ['Content-Type' => 'application/problem+json'],
            ));
        }
    }

    private function needsProtection(Request $request): bool
    {
        return \in_array($request->getMethod(), self::UNSAFE_METHODS, true)
            && str_starts_with($request->getPathInfo(), '/api/v1');
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 40]];
    }
}
