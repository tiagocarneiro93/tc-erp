<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use App\Platform\Application\Command\ChangePassword;
use App\Platform\Application\Command\ConfirmPasswordReset;
use App\Platform\Application\Command\RequestPasswordReset;
use App\Platform\Application\Security\CurrentUserId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Domain exceptions raised by command handlers are left to propagate to
 * {@see \App\Shared\Infrastructure\Http\ProblemDetailsExceptionListener},
 * which unwraps Messenger's HandlerFailedException and formats the result
 * as RFC 9457 problem+json (CLAUDE.md task 0.11).
 */
#[OA\Tag(name: 'Auth')]
final class AuthController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly CurrentUserId $currentUserId,
    ) {
    }

    /**
     * Authenticates with email and password and starts a session cookie.
     */
    // Never actually executed: json_login's authenticator (security.yaml)
    // intercepts this route before the controller runs; the route still has
    // to exist because Symfony's router runs before the firewall in the
    // kernel.request chain, so check_path needs something to match. The
    // OpenAPI attributes below document json_login's actual contract, not
    // this dummy body.
    #[Route('/api/v1/auth/login', name: 'auth_login', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'password', type: 'string'),
    ]))]
    #[OA\Response(response: 200, description: 'Authenticated.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'authenticated', type: 'boolean'),
        new OA\Property(property: 'must_change_password', type: 'boolean'),
    ]))]
    #[OA\Response(response: 401, description: 'Wrong email or password.')]
    public function login(): never
    {
        throw new \LogicException('Unreachable: the json_login authenticator handles this route.');
    }

    /**
     * Ends the session.
     */
    // Never actually executed: the logout listener (security.yaml)
    // intercepts this route before the controller runs.
    #[Route('/api/v1/auth/logout', name: 'auth_logout', methods: ['POST'])]
    #[OA\Response(response: 302, description: 'Session cleared.')]
    public function logout(): never
    {
        throw new \LogicException('Unreachable: the logout listener handles this route.');
    }

    #[Route('/api/v1/auth/change-password', name: 'auth_change_password', methods: ['POST'])]
    #[OA\Response(response: 204, description: 'Password changed.')]
    #[OA\Response(response: 422, description: 'Wrong current password, or the new one is empty.')]
    public function changePassword(#[MapRequestPayload] ChangePasswordRequest $request): Response
    {
        $this->commandBus->dispatch(new ChangePassword(
            $this->currentUserId->id(),
            $request->currentPassword,
            $request->newPassword,
        ));

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/auth/password/reset', name: 'auth_request_password_reset', methods: ['POST'])]
    #[OA\Response(response: 202, description: 'Always returned, whether or not the email matched a user (no account enumeration).')]
    public function requestPasswordReset(#[MapRequestPayload] RequestPasswordResetRequest $request): Response
    {
        $this->commandBus->dispatch(new RequestPasswordReset($request->email));

        // Always the same response, whether or not the email matched a user.
        return new JsonResponse(null, 202);
    }

    #[Route('/api/v1/auth/password/reset/confirm', name: 'auth_confirm_password_reset', methods: ['POST'])]
    #[OA\Response(response: 204, description: 'Password set.')]
    #[OA\Response(response: 422, description: 'The token is invalid, expired or already used, or the new password is empty.')]
    public function confirmPasswordReset(#[MapRequestPayload] ConfirmPasswordResetRequest $request): Response
    {
        $this->commandBus->dispatch(new ConfirmPasswordReset($request->token, $request->newPassword));

        return new JsonResponse(null, 204);
    }
}
