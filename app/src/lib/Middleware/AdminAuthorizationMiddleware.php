<?php

declare(strict_types=1);

/**
 * Administrative authorization middleware.
 *
 * Allows access only to authenticated users who have administrative
 * privileges. Superadmin users are always allowed. Non-admin users are
 * redirected to the authenticated dashboard.
 */

namespace App\Middleware;

use Lampfire\Services\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AdminAuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * @var UserService Service used to evaluate admin authorization.
     */
    private UserService $userService;

    /**
     * Creates the middleware.
     *
     * @param UserService $userService Service used to evaluate admin authorization.
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Ensures the current authenticated user can access admin routes.
     *
     * @param ServerRequestInterface  $request The incoming request.
     * @param RequestHandlerInterface $handler The downstream request handler.
     * @return ResponseInterface The downstream response or an access-denied redirect.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $authUserId = $request->getAttribute('auth_user_id');
        $authUsername = $request->getAttribute('auth_username');

        if (is_string($authUserId) === false || $authUserId === '') {
            return $this->deny();
        }

        if (is_string($authUsername) === false || $authUsername === '') {
            return $this->deny();
        }

        $isAuthorized = $this->userService->isAuthorizedForUserWrite(
            $authUserId,
            $authUsername,
            'ADMIN_PERMISSION_USER_READ'
        );

        if ($isAuthorized === false) {
            return $this->deny();
        }

        return $handler->handle($request);
    }

    /**
     * Redirects unauthorized users away from the admin panel.
     *
     * @return ResponseInterface A redirect response.
     */
    private function deny(): ResponseInterface
    {
        return (new Response(302))->withHeader('Location', '/dashboard');
    }
}
