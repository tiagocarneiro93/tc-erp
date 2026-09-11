<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use App\Platform\Application\Command\ChangeMemberRole;
use App\Platform\Application\Command\InviteUserToCompany;
use App\Platform\Application\Command\RemoveMember;
use App\Platform\Application\Security\CurrentUserId;
use App\Platform\Domain\Exception\AlreadyAMember;
use App\Platform\Domain\Exception\NotAMember;
use App\Platform\Domain\Exception\PermissionDenied;
use App\Platform\Domain\Exception\UnknownRole;
use App\Platform\Domain\UserId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * `{companyId}` is resolved and authorized by `CompanyRouteListener`
 * (membership) before these actions run; the `members.manage` permission
 * check happens in each command handler (docs/PLAN.md task 0.9/0.10).
 */
final class CompanyUsersController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly CurrentUserId $currentUserId,
    ) {
    }

    #[Route('/api/v1/companies/{companyId}/users', name: 'company_users_invite', methods: ['POST'])]
    public function invite(#[MapRequestPayload] InviteUserRequest $request, Request $httpRequest): Response
    {
        try {
            $this->dispatch(new InviteUserToCompany(
                $this->currentUserId->id(),
                $request->email,
                $request->name,
                $request->role,
                $httpRequest->getClientIp() ?? '',
                $httpRequest->headers->get('User-Agent', ''),
            ));
        } catch (PermissionDenied $e) {
            return $this->problem($e->getMessage(), 403);
        } catch (UnknownRole $e) {
            return $this->problem($e->getMessage(), 422);
        } catch (AlreadyAMember $e) {
            return $this->problem($e->getMessage(), 409);
        }

        return new JsonResponse(null, 201);
    }

    #[Route('/api/v1/companies/{companyId}/users/{userId}', name: 'company_users_change_role', methods: ['PUT'])]
    public function changeRole(string $userId, #[MapRequestPayload] ChangeMemberRoleRequest $request, Request $httpRequest): Response
    {
        try {
            $this->dispatch(new ChangeMemberRole(
                $this->currentUserId->id(),
                UserId::fromString($userId),
                $request->role,
                $httpRequest->getClientIp() ?? '',
                $httpRequest->headers->get('User-Agent', ''),
            ));
        } catch (\InvalidArgumentException) {
            return $this->problem('No such user.', 404);
        } catch (PermissionDenied $e) {
            return $this->problem($e->getMessage(), 403);
        } catch (UnknownRole $e) {
            return $this->problem($e->getMessage(), 422);
        } catch (NotAMember $e) {
            return $this->problem($e->getMessage(), 404);
        }

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/users/{userId}', name: 'company_users_remove', methods: ['DELETE'])]
    public function remove(string $userId, Request $httpRequest): Response
    {
        try {
            $this->dispatch(new RemoveMember(
                $this->currentUserId->id(),
                UserId::fromString($userId),
                $httpRequest->getClientIp() ?? '',
                $httpRequest->headers->get('User-Agent', ''),
            ));
        } catch (\InvalidArgumentException) {
            return $this->problem('No such user.', 404);
        } catch (PermissionDenied $e) {
            return $this->problem($e->getMessage(), 403);
        } catch (NotAMember $e) {
            return $this->problem($e->getMessage(), 404);
        }

        return new JsonResponse(null, 204);
    }

    /**
     * Messenger wraps handler exceptions in HandlerFailedException; unwrap
     * so callers can catch the real domain exception.
     */
    private function dispatch(object $command): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            foreach ($e->getWrappedExceptions() as $wrapped) {
                throw $wrapped;
            }

            throw $e;
        }
    }

    private function problem(string $detail, int $status): JsonResponse
    {
        return new JsonResponse(
            ['title' => $detail, 'status' => $status],
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
