<?php

declare(strict_types=1);

/**
 * Admin authentication controller.
 *
 * Handles the login form, login submission, logout, and the dashboard
 * page. Session tokens are stored as HTTP-only cookies using Paseto.
 */

namespace App\Controllers\Auth;

use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use Lampfire\Controllers\AbstractAdminController;
use Lampfire\Routing\Route;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AuthController extends AbstractAdminController
{
    protected string $routePrefix = '/admin';

    private const COOKIE_NAME = 'session_token';
    private const SESSION_LIFETIME_SECONDS = 7200;

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

    /**
     * GET /admin/login - Renders the login form.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response The rendered login page.
     */
    #[Route('GET', '/login')]
    public function showLoginForm(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'admin/login.twig', [
            'pageTitle' => 'Admin Login',
        ]);
    }

    /**
     * POST /admin/login - Processes the login form submission.
     *
     * On success, sets an HTTP-only cookie with the Paseto token and
     * redirects to the admin dashboard. On failure, re-renders the login
     * form with an error message.
     *
     * @param Request  $request  The incoming request with form data.
     * @param Response $response The outgoing response.
     * @return Response A redirect or re-rendered login page.
     */
    #[Route('POST', '/login')]
    public function handleLogin(Request $request, Response $response): Response
    {
        $body     = $request->getParsedBody();
        $username = $this->formString($body, 'username');
        $password = $this->formRawString($body, 'password');

        if ($username === '' || $password === '') {
            return $this->twig->render($response, 'admin/login.twig', [
                'pageTitle' => 'Admin Login',
                'error'     => 'Username and password are required.',
            ]);
        }

        $result = $this->authService->authenticate($username, $password);

        if ($result === null) {
            return $this->twig->render($response, 'admin/login.twig', [
                'pageTitle' => 'Admin Login',
                'error'     => 'Invalid username or password.',
            ]);
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
            ->withHeader('Location', '/admin/dashboard')
            ->withStatus(302);
    }

    /**
     * GET /admin/logout - Clears the session cookie and redirects to login.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A redirect to the login page.
     */
    #[Route('GET', '/logout')]
    public function handleLogout(Request $request, Response $response): Response
    {
        $cookieHeader = sprintf(
            '%s=; Path=/; HttpOnly; SameSite=Lax; Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            self::COOKIE_NAME
        );

        return $response
            ->withHeader('Set-Cookie', $cookieHeader)
            ->withHeader('Location', '/admin/login')
            ->withStatus(302);
    }

    /**
     * GET /admin/dashboard - Renders the admin dashboard landing page.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response The rendered dashboard page.
     */
    #[Route('GET', '/dashboard', middleware: [AuthMiddleware::class])]
    public function showDashboard(Request $request, Response $response): Response
    {
        $username = $request->getAttribute('auth_username');
        $displayName = is_string($username) ? $username : 'Admin';

        return $this->twig->render($response, 'admin/dashboard.twig', [
            'pageTitle'  => 'Dashboard',
            'username'   => $displayName,
            'csrf_token' => $this->getCsrfToken($request),
        ]);
    }
}
