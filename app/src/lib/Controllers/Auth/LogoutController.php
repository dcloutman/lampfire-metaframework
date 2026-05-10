<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use Lampfire\Controllers\AbstractAdminController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class LogoutController extends AbstractAdminController
{
    protected string $routePrefix = '/admin/logout';

    private const COOKIE_NAME = 'session_token';
    private const MESSAGE_SIGN_OUT_SUCCESS = 'You signed out successfully.';

    public function __construct(Twig $twig)
    {
        parent::__construct($twig);
    }

    public function get(Request $request, Response $response): Response
    {
        $cookieHeader = sprintf(
            '%s=; Path=/; HttpOnly; SameSite=Lax; Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            self::COOKIE_NAME
        );

        return $response
            ->withHeader('Set-Cookie', $cookieHeader)
            ->withHeader('Location', $this->urlWithFlash('/admin/login', self::MESSAGE_SIGN_OUT_SUCCESS, null))
            ->withStatus(302);
    }
}
