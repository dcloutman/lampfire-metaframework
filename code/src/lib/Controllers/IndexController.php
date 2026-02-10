<?php

declare(strict_types=1);

/**
 * Public homepage controller.
 *
 * Renders the landing page with a login form and processes login
 * submissions. On successful authentication, the user is redirected
 * to the user dashboard with a session cookie.
 */

namespace App\Controllers;

use App\Services\AuthService;
use Lampfire\Controllers\AbstractAdminController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class IndexController extends AbstractAdminController
{
    protected string $routePrefix = '/';

    private const COOKIE_NAME = 'session_token';
    private const SESSION_LIFETIME_SECONDS = 7200;

    /**
     * @var AuthService Service for Paseto token authentication.
     */
    private AuthService $authService;

    /**
     * Creates the homepage controller.
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
     * GET / - Renders the public homepage with a login form.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response The rendered homepage.
     */
    public function get(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'home.twig', [
            'pageTitle' => 'Lampfire',
        ]);
    }

    /**
     * POST / - Processes the homepage login form submission.
     *
     * On success, sets an HTTP-only cookie with the Paseto token and
     * redirects to the user dashboard. On failure, re-renders the
     * homepage with an error message.
     *
     * @param Request  $request  The incoming request with form data.
     * @param Response $response The outgoing response.
     * @return Response A redirect or re-rendered homepage.
     */
    public function post(Request $request, Response $response): Response
    {
        $body     = $request->getParsedBody();
        $username = $this->formString($body, 'username');
        $password = $this->formRawString($body, 'password');

        if ($username === '' || $password === '') {
            return $this->twig->render($response, 'home.twig', [
                'pageTitle' => 'Lampfire',
                'error'     => 'Username and password are required.',
            ]);
        }

        $result = $this->authService->authenticate($username, $password);

        if ($result === null) {
            return $this->twig->render($response, 'home.twig', [
                'pageTitle' => 'Lampfire',
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
            ->withHeader('Location', '/dashboard')
            ->withStatus(302);
    }
}
