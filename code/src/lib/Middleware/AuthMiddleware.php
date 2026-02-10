<?php
/**
 * Authentication Middleware.
 *
 * Validates Paseto tokens from either an HTTP-only cookie (admin panel)
 * or an Authorization Bearer header (API). Injects the authenticated
 * user identity into request attributes for downstream handlers.
 *
 * @author  David Cloutman
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

/**
 * Middleware that validates Paseto authentication tokens.
 *
 * Admin routes use an HTTP-only session cookie. API routes use the
 * Authorization Bearer header. When the token is valid, the decoded
 * claims are attached to the request attributes.
 */
class AuthMiddleware implements MiddlewareInterface
{
    private const COOKIE_NAME = 'session_token';

    /**
     * @var AuthService Service for token parsing and validation.
     */
    private AuthService $authService;

    /**
     * @var LoggerInterface Logger for authentication events.
     */
    private LoggerInterface $logger;

    /**
     * Creates the authentication middleware.
     *
     * @param AuthService     $authService Service for Paseto token validation.
     * @param LoggerInterface $logger      Logger instance.
     */
    public function __construct(AuthService $authService, LoggerInterface $logger)
    {
        $this->authService = $authService;
        $this->logger = $logger;
    }

    /**
     * Extracts and validates the Paseto token, then delegates to the handler.
     *
     * @param ServerRequestInterface  $request The incoming request.
     * @param RequestHandlerInterface $handler The next handler in the stack.
     * @return ResponseInterface The response from downstream or an error response.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->denyAccess($request, 'No authentication token was provided.');
        }

        $claims = $this->authService->parseToken($token);

        if ($claims === null) {
            $this->logger->warning('Token validation failed for request.', [
                'path' => $request->getUri()->getPath(),
            ]);
            return $this->denyAccess($request, 'The authentication token is invalid or expired.');
        }

        // Attach authenticated identity to the request for downstream use.
        $request = $request->withAttribute('auth_user_id', $claims['user_id']);
        $request = $request->withAttribute('auth_username', $claims['username']);
        $request = $request->withAttribute('auth_csrf_token', $claims['csrf_token']);

        $this->logger->debug('User authenticated.', [
            'user_id' => $claims['user_id'],
        ]);

        return $handler->handle($request);
    }

    /**
     * Extracts the Paseto token from the cookie or the Authorization header.
     *
     * The cookie is checked first (admin sessions), then the Bearer header
     * (API clients).
     *
     * @param ServerRequestInterface $request The incoming request.
     * @return string|null The token string or null when none was found.
     */
    private function extractToken(ServerRequestInterface $request): ?string
    {
        // Check for session cookie first.
        $cookies = $request->getCookieParams();
        if (array_key_exists(self::COOKIE_NAME, $cookies) && is_string($cookies[self::COOKIE_NAME])) {
            $cookieValue = $cookies[self::COOKIE_NAME];
            if ($cookieValue !== '') {
                return $cookieValue;
            }
        }

        // Fall back to Authorization Bearer header.
        $authHeader = $request->getHeaderLine('Authorization');
        if (is_string($authHeader) && $authHeader !== '') {
            if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Returns an appropriate denial response based on the request path.
     *
     * API requests receive a 401 JSON body. Admin browser requests are
     * redirected to the login page.
     *
     * @param ServerRequestInterface $request The original request.
     * @param string                 $message A human-readable error message.
     * @return ResponseInterface The denial response.
     */
    private function denyAccess(ServerRequestInterface $request, string $message): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (str_starts_with($path, '/api/')) {
            $response = new Response(401);
            $response->getBody()->write(json_encode([
                'error'   => 'Unauthorized',
                'message' => $message,
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Browser requests redirect to the homepage login form.
        $response = new Response(302);
        return $response->withHeader('Location', '/');
    }
}
