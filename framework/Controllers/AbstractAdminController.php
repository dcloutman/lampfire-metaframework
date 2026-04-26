<?php

declare(strict_types=1);

/**
 * Abstract base for admin panel controllers that render Twig templates.
 *
 * Provides template-rendering helpers and form-data extraction methods
 * so that concrete admin controllers do not duplicate boilerplate.
 */

namespace Lampfire\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

abstract class AbstractAdminController extends AbstractController
{
    /**
     * @var Twig The Twig template renderer.
     */
    protected Twig $twig;

    /**
     * Creates the admin controller.
     *
     * @param Twig $twig The Twig view renderer.
     */
    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Extracts the CSRF token from the request attributes.
     *
     * @param Request $request The incoming request.
     * @return string The CSRF token string.
     */
    protected function getCsrfToken(Request $request): string
    {
        $token = $request->getAttribute('auth_csrf_token');

        return is_string($token) ? $token : '';
    }

    /**
     * Extracts a required string field from the parsed form body.
     *
     * Returns an empty string when the field is missing or not a string.
     *
     * @param array<string, mixed>|null $body      The parsed request body.
     * @param string                    $fieldName The field to extract.
     * @return string The trimmed string value.
     */
    protected function formString(?array $body, string $fieldName): string
    {
        if ($body === null) {
            return '';
        }

        if (array_key_exists($fieldName, $body) === false) {
            return '';
        }

        $value = $body[$fieldName];
        if (is_string($value) === false) {
            return '';
        }

        return trim($value);
    }

    /**
     * Extracts a raw string field from the parsed form body without trimming.
     *
     * Useful for password fields where whitespace may be intentional.
     *
     * @param array<string, mixed>|null $body      The parsed request body.
     * @param string                    $fieldName The field to extract.
     * @return string The raw string value.
     */
    protected function formRawString(?array $body, string $fieldName): string
    {
        if ($body === null) {
            return '';
        }

        if (array_key_exists($fieldName, $body) === false) {
            return '';
        }

        $value = $body[$fieldName];
        if (is_string($value) === false) {
            return '';
        }

        return $value;
    }

    /**
     * Returns a 404 plain-text response.
     *
     * @param Response $response The outgoing response.
     * @param string   $message  The error message.
     * @return Response The 404 response.
     */
    protected function notFound(Response $response, string $message = 'The requested resource was not found.'): Response
    {
        $response->getBody()->write($message);

        return $response->withStatus(404);
    }

    /**
     * Returns a redirect response.
     *
     * @param Response $response The outgoing response.
     * @param string   $url      The redirect target URL.
     * @param int      $status   The HTTP redirect status code.
     * @return Response The redirect response.
     */
    protected function redirect(Response $response, string $url, int $status = 302): Response
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus($status);
    }

    /**
     * Returns normalized flash-message view data from query parameters.
     *
     * @param Request $request The incoming request.
     * @return array{success: string|null, error: string|null} Flash data.
     */
    protected function getFlashViewData(Request $request): array
    {
        $queryParams = $request->getQueryParams();

        $success = $queryParams['success'] ?? null;
        $error = $queryParams['error'] ?? null;

        $successText = is_string($success) ? trim($success) : '';
        $errorText = is_string($error) ? trim($error) : '';

        return [
            'success' => $successText !== '' ? $successText : null,
            'error' => $errorText !== '' ? $errorText : null,
        ];
    }

    /**
     * Returns a URL with optional success and error flash messages.
     *
     * @param string $url The base URL.
     * @param string|null $successMessage The success message.
     * @param string|null $errorMessage The error message.
     * @return string The URL including flash query parameters.
     */
    protected function urlWithFlash(
        string $url,
        ?string $successMessage = null,
        ?string $errorMessage = null
    ): string {
        $urlParts = parse_url($url);

        if ($urlParts === false) {
            return $url;
        }

        $path = (string) ($urlParts['path'] ?? '');
        $existingQuery = '';

        if (array_key_exists('query', $urlParts) && is_string($urlParts['query'])) {
            $existingQuery = $urlParts['query'];
        }

        $query = [];
        if ($existingQuery !== '') {
            parse_str($existingQuery, $query);
        }

        if (is_string($successMessage) && trim($successMessage) !== '') {
            $query['success'] = trim($successMessage);
        }

        if (is_string($errorMessage) && trim($errorMessage) !== '') {
            $query['error'] = trim($errorMessage);
        }

        if (count($query) === 0) {
            return $path;
        }

        return $path . '?' . http_build_query($query);
    }

    /**
     * Returns a redirect response with a success flash message.
     *
     * @param Response $response The outgoing response.
     * @param string $url The redirect target URL.
     * @param string $message The success message.
     * @param int $status The HTTP redirect status code.
     * @return Response The redirect response.
     */
    protected function redirectWithSuccess(
        Response $response,
        string $url,
        string $message,
        int $status = 302
    ): Response {
        return $this->redirect($response, $this->urlWithFlash($url, $message, null), $status);
    }

    /**
     * Returns a redirect response with an error flash message.
     *
     * @param Response $response The outgoing response.
     * @param string $url The redirect target URL.
     * @param string $message The error message.
     * @param int $status The HTTP redirect status code.
     * @return Response The redirect response.
     */
    protected function redirectWithError(
        Response $response,
        string $url,
        string $message,
        int $status = 302
    ): Response {
        return $this->redirect($response, $this->urlWithFlash($url, null, $message), $status);
    }
}
