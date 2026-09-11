<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Http;

use App\Platform\Infrastructure\Security\CompanyVoter;
use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Resolves `{companyId}` on every `/api/v1/companies/{companyId}/...` route
 * (technical-scope.md §5.3, §9.1) into the {@see RequestCompanyContext},
 * after checking membership via {@see CompanyVoter}.
 *
 * 404/403 policy: a company that doesn't exist and a company the caller
 * isn't a member of both respond 404 — never reveal that a company exists
 * to someone outside it. 403 is reserved for a confirmed member who lacks a
 * specific permission, decided later by controllers voting on that
 * permission (once one exists to check, from task 0.10 onward).
 *
 * Runs after the firewall (priority 8) so the user is authenticated, and
 * after `MustChangePasswordListener` (priority 0) so that block always wins.
 */
final class CompanyRouteListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestCompanyContext $companyContext,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $raw = $event->getRequest()->attributes->get('companyId');

        if (!\is_string($raw)) {
            return;
        }

        try {
            $companyId = CompanyId::fromString($raw);
        } catch (\InvalidArgumentException) {
            $event->setResponse($this->notFound());

            return;
        }

        if (!$this->authorizationChecker->isGranted(CompanyVoter::MEMBER, $companyId)) {
            $event->setResponse($this->notFound());

            return;
        }

        $this->companyContext->set($companyId);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(
            [
                'type' => 'https://tc-erp.example/problems/company-not-found',
                'title' => 'No such company.',
                'status' => 404,
            ],
            404,
            ['Content-Type' => 'application/problem+json'],
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', -10]];
    }
}
