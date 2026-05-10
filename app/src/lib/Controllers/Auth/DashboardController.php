<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Middleware\AdminAuthorizationMiddleware;
use App\Middleware\AuthMiddleware;
use Lampfire\Controllers\AbstractAdminController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DashboardController extends AbstractAdminController
{
    protected string $routePrefix = '/admin/dashboard';
    protected array $routeMiddleware = [AdminAuthorizationMiddleware::class, AuthMiddleware::class];

    public function __construct(Twig $twig)
    {
        parent::__construct($twig);
    }

    public function get(Request $request, Response $response): Response
    {
        $username = $request->getAttribute('auth_username');
        $displayName = is_string($username) ? $username : 'Admin';

        return $this->twig->render($response, 'admin/dashboard.twig', array_merge([
            'pageTitle' => 'Dashboard',
            'username' => $displayName,
            'csrf_token' => $this->getCsrfToken($request),
        ], $this->getFlashViewData($request)));
    }
}
