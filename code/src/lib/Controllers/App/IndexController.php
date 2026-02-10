<?php

declare(strict_types=1);

/**
 * Authenticated user dashboard controller.
 *
 * Renders the post-login landing page for all authenticated users.
 * This is the general-purpose entry point after sign-in. Users with
 * administrative privileges can navigate to the admin panel from here.
 */

namespace App\Controllers\App;

use App\Middleware\AuthMiddleware;
use Lampfire\Controllers\AbstractAdminController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class IndexController extends AbstractAdminController
{
    protected string $routePrefix = '/dashboard';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * Creates the dashboard controller.
     *
     * @param Twig $twig The Twig view renderer.
     */
    public function __construct(Twig $twig)
    {
        parent::__construct($twig);
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
        $username = $request->getAttribute('auth_username');
        $displayName = is_string($username) ? $username : 'User';

        return $this->twig->render($response, 'dashboard.twig', [
            'pageTitle' => 'Dashboard',
            'username'  => $displayName,
        ]);
    }
}
