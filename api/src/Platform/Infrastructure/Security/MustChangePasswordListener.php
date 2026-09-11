<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Blocks every /api/v1 endpoint except a small allowlist while the
 * authenticated user still has User::mustChangePassword() (CLAUDE.md task
 * 0.8: "must_change_password enforced on first login").
 */
final class MustChangePasswordListener implements EventSubscriberInterface
{
    private const ALLOWED_PATHS = [
        '/api/v1/health',
        '/api/v1/auth/login',
        '/api/v1/auth/logout',
        '/api/v1/auth/change-password',
    ];

    public function __construct(private readonly Security $security)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (\in_array($request->getPathInfo(), self::ALLOWED_PATHS, true)) {
            return;
        }

        if (!str_starts_with($request->getPathInfo(), '/api/v1')) {
            return;
        }

        $securityUser = $this->security->getUser();

        if (!$securityUser instanceof SecurityUser || !$securityUser->user()->mustChangePassword()) {
            return;
        }

        $event->setResponse(new JsonResponse(
            [
                'type' => 'https://tc-erp.example/problems/password-change-required',
                'title' => 'You must change your password before continuing.',
                'status' => 403,
            ],
            403,
            ['Content-Type' => 'application/problem+json'],
        ));
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 0]];
    }
}
