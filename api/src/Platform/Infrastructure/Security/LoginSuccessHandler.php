<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Without an explicit handler, json_login's default behaviour is to let the
 * request fall through to whatever controller matches check_path — there is
 * none here on purpose (AuthController::login() only exists so the router
 * has something to match), so a handler is required to actually respond.
 */
final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        $mustChangePassword = $user instanceof SecurityUser && $user->user()->mustChangePassword();

        return new JsonResponse(['authenticated' => true, 'must_change_password' => $mustChangePassword]);
    }
}
