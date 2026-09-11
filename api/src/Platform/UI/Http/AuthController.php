<?php

declare(strict_types=1);

namespace App\Platform\UI\Http;

use App\Platform\Application\Command\ChangePassword;
use App\Platform\Application\Command\ConfirmPasswordReset;
use App\Platform\Application\Command\RequestPasswordReset;
use App\Platform\Application\Security\CurrentUserId;
use App\Platform\Domain\Exception\EmptyPassword;
use App\Platform\Domain\Exception\InvalidCurrentPassword;
use App\Platform\Domain\Exception\InvalidOrExpiredResetToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly CurrentUserId $currentUserId,
    ) {
    }

    /**
     * Never actually executed: json_login's authenticator (security.yaml)
     * intercepts POST /api/v1/auth/login before the controller is invoked.
     * The route still has to exist — Symfony's router runs before the
     * firewall in the kernel.request chain, so check_path needs a match.
     */
    #[Route('/api/v1/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Unreachable: the json_login authenticator handles this route.');
    }

    /**
     * Never actually executed: the logout listener (security.yaml)
     * intercepts POST /api/v1/auth/logout before the controller is invoked.
     */
    #[Route('/api/v1/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Unreachable: the logout listener handles this route.');
    }

    #[Route('/api/v1/auth/change-password', name: 'auth_change_password', methods: ['POST'])]
    public function changePassword(#[MapRequestPayload] ChangePasswordRequest $request): Response
    {
        try {
            $this->dispatch(new ChangePassword(
                $this->currentUserId->id(),
                $request->currentPassword,
                $request->newPassword,
            ));
        } catch (InvalidCurrentPassword|EmptyPassword $e) {
            return $this->problem($e->getMessage(), 422);
        }

        return new JsonResponse(null, 204);
    }

    #[Route('/api/v1/auth/password/reset', name: 'auth_request_password_reset', methods: ['POST'])]
    public function requestPasswordReset(#[MapRequestPayload] RequestPasswordResetRequest $request): Response
    {
        $this->dispatch(new RequestPasswordReset($request->email));

        // Always the same response, whether or not the email matched a user.
        return new JsonResponse(null, 202);
    }

    #[Route('/api/v1/auth/password/reset/confirm', name: 'auth_confirm_password_reset', methods: ['POST'])]
    public function confirmPasswordReset(#[MapRequestPayload] ConfirmPasswordResetRequest $request): Response
    {
        try {
            $this->dispatch(new ConfirmPasswordReset($request->token, $request->newPassword));
        } catch (InvalidOrExpiredResetToken|EmptyPassword $e) {
            return $this->problem($e->getMessage(), 422);
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
