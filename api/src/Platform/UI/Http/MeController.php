<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use App\Platform\Application\Query\GetMe;
use App\Platform\Application\Query\MeView;
use App\Platform\Application\Security\CurrentUserId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Me')]
final class MeController
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly CurrentUserId $currentUserId,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/me', name: 'me', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The authenticated user, their companies, role and permissions.')]
    #[OA\Response(response: 401, description: 'Not authenticated.')]
    #[OA\Response(response: 403, description: 'must_change_password is still true.')]
    public function __invoke(): JsonResponse
    {
        /** @var MeView $me */
        $me = $this->handle(new GetMe($this->currentUserId->id()));

        return new JsonResponse([
            'user' => [
                'id' => $me->userId,
                'email' => $me->email,
                'name' => $me->name,
                'must_change_password' => $me->mustChangePassword,
            ],
            'companies' => array_map(static fn ($c) => [
                'id' => $c->companyId,
                'legal_name' => $c->legalName,
                'role' => $c->role,
                'permissions' => $c->permissions,
            ], $me->companies),
        ]);
    }
}
