<?php

declare(strict_types=1);

/**
 * Authenticated user dashboard controller.
 *
 * Renders the post-login landing page for all authenticated users.
 * This is the general-purpose entry point after sign-in. Users with
 * administrative privileges can navigate to the admin panel from here.
 */

namespace App\Controllers\Dashboard;

use App\Middleware\AuthMiddleware;
use Lampfire\Controllers\AbstractAdminController;
use Lampfire\Services\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class IndexController extends AbstractAdminController
{
    protected string $routePrefix = '/dashboard';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * @var UserService Service used to evaluate admin-panel access.
     */
    private UserService $userService;

    /**
     * Creates the dashboard controller.
     *
     * @param Twig $twig The Twig view renderer.
     */
    public function __construct(UserService $userService, Twig $twig)
    {
        parent::__construct($twig);
        $this->userService = $userService;
    }

    /**
     * GET /dashboard - Renders the authenticated user dashboard.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response The rendered dashboard page.
     */
    public function get(Request $request, Response $response): Response
    {
        $authUserId = $request->getAttribute('auth_user_id');
        $username = $request->getAttribute('auth_username');
        $displayName = is_string($username) ? $username : 'User';
        $canAccessAdminPanel = false;

        if (is_string($authUserId) && $authUserId !== '' && is_string($username) && $username !== '') {
            $canAccessAdminPanel = $this->userService->isAuthorizedForUserWrite(
                $authUserId,
                $username,
                'ADMIN_PERMISSION_USER_READ'
            );
        }

        return $this->twig->render($response, 'dashboard.twig', array_merge([
            'pageTitle'           => 'Dashboard',
            'username'            => $displayName,
            'canAccessAdminPanel' => $canAccessAdminPanel,
        ], $this->getFlashViewData($request)));
    }
}
