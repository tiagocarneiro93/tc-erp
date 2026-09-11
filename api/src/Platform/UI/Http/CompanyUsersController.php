<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use App\Platform\Application\Command\ChangeMemberRole;
use App\Platform\Application\Command\InviteUserToCompany;
use App\Platform\Application\Command\RemoveMember;
use App\Platform\Application\Security\CurrentUserId;
use App\Platform\Domain\UserId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * `{companyId}` is resolved and authorized by `CompanyRouteListener`
 * (membership) before these actions run; the `members.manage` permission
 * check happens in each command handler (docs/PLAN.md task 0.9/0.10).
 * Domain exceptions are left to propagate to
 * {@see \App\Shared\Infrastructure\Http\ProblemDetailsExceptionListener}
 * (task 0.11).
 */
#[OA\Tag(name: 'Companies')]
final class CompanyUsersController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly CurrentUserId $currentUserId,
    ) {
    }

    #[Route('/api/v1/companies/{companyId}/users', name: 'company_users_invite', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Membership created. A brand-new user is emailed a set-password link.')]
    #[OA\Response(response: 403, description: 'The caller lacks the members.manage permission.')]
    #[OA\Response(response: 404, description: 'The caller is not a member of this company.')]
    #[OA\Response(response: 409, description: 'This user is already a member of this company.')]
    #[OA\Response(response: 422, description: 'Unknown role, or the request payload failed validation.')]
    public function invite(#[MapRequestPayload] InviteUserRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new InviteUserToCompany(
            $this->currentUserId->id(),
            $request->email,
            $request->name,
            $request->role,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 201);
    }

    #[Route('/api/v1/companies/{companyId}/users/{userId}', name: 'company_users_change_role', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Role changed.')]
    #[OA\Response(response: 403, description: 'The caller lacks the members.manage permission.')]
    #[OA\Response(response: 404, description: 'The caller is not a member of this company, or the target user is not either.')]
    #[OA\Response(response: 422, description: 'Unknown role.')]
    public function changeRole(string $userId, #[MapRequestPayload] ChangeMemberRoleRequest $request, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new ChangeMemberRole(
            $this->currentUserId->id(),
            $this->parseUserId($userId),
            $request->role,
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/companies/{companyId}/users/{userId}', name: 'company_users_remove', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Member removed.')]
    #[OA\Response(response: 403, description: 'The caller lacks the members.manage permission.')]
    #[OA\Response(response: 404, description: 'The caller is not a member of this company, or the target user is not either.')]
    public function remove(string $userId, Request $httpRequest): Response
    {
        $this->commandBus->dispatch(new RemoveMember(
            $this->currentUserId->id(),
            $this->parseUserId($userId),
            $httpRequest->getClientIp() ?? '',
            $httpRequest->headers->get('User-Agent', ''),
        ));

        return new JsonResponse(null, 204);
    }

    private function parseUserId(string $userId): UserId
    {
        try {
            return UserId::fromString($userId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('No such user.');
        }
    }
}
