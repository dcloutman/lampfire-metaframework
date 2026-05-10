<?php

declare(strict_types=1);

/**
 * Admin authentication controller.
 *
 * Handles the login form, login submission, logout, and the dashboard
 * page. Session tokens are stored as HTTP-only cookies using Paseto.
 */

namespace App\Controllers\Auth;

use App\Services\AuthService;
use Lampfire\Controllers\AbstractAdminController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AuthController extends AbstractAdminController
{
    protected string $routePrefix = '/admin/login';

    private const COOKIE_NAME = 'session_token';
    private const SESSION_LIFETIME_SECONDS = 7200;
    private const MESSAGE_USERNAME_PASSWORD_REQUIRED = 'Username and password are required.';
    private const MESSAGE_INVALID_USERNAME_PASSWORD = 'Invalid username or password.';
    private const MESSAGE_SIGN_IN_SUCCESS = 'You signed in successfully.';

    /**
     * @var AuthService Service for Paseto token authentication.
     */
    private AuthService $authService;

    /**
     * Creates the admin authentication controller.
     *
     * @param AuthService $authService The authentication service.
     * @param Twig        $twig        The Twig view renderer.
     */
    public function __construct(AuthService $authService, Twig $twig)
    {
        parent::__construct($twig);
        $this->authService = $authService;
    }

    public function get(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'admin/login.twig', array_merge([
            'pageTitle' => 'Admin Login',
        ], $this->getFlashViewData($request)));
    }

    public function post(Request $request, Response $response): Response
    {
        $body     = $request->getParsedBody();
        $username = $this->formString($body, 'username');
        $password = $this->formRawString($body, 'password');

        if ($username === '' || $password === '') {
            return $this->redirectWithError(
                $response,
                '/admin/login',
                self::MESSAGE_USERNAME_PASSWORD_REQUIRED
            );
        }

        $result = $this->authService->authenticate($username, $password);

        if ($result === null) {
            return $this->redirectWithError(
                $response,
                '/admin/login',
                self::MESSAGE_INVALID_USERNAME_PASSWORD
            );
        }

        // Set the Paseto token in a secure, HTTP-only cookie.
        $cookieValue = urlencode($result['token']);
        $expires     = gmdate('D, d M Y H:i:s T', time() + self::SESSION_LIFETIME_SECONDS);
        $cookieHeader = sprintf(
            '%s=%s; Path=/; HttpOnly; SameSite=Lax; Expires=%s',
            self::COOKIE_NAME,
            $cookieValue,
            $expires
        );

        return $response
            ->withHeader('Set-Cookie', $cookieHeader)
            ->withHeader('Location', $this->urlWithFlash('/admin/dashboard', self::MESSAGE_SIGN_IN_SUCCESS, null))
            ->withStatus(302);
    }
}
