<?php

declare(strict_types=1);

/**
 * Abstract base for RESTful API controllers.
 *
 * Provides JSON response helpers and request-body extraction methods so
 * that concrete API controllers have a consistent error format and do
 * not duplicate response-building boilerplate.
 */

namespace Lampfire\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

abstract class AbstractRestController extends AbstractController
{
    /**
     * Writes a JSON payload to the response body and sets the correct
     * content type header.
     *
     * @param Response $response   The outgoing response.
     * @param array<string, mixed> $data       The data to encode.
     * @param int $statusCode The HTTP status code.
     * @return Response The JSON response.
     */
    protected function prepareJsonResponse(Response $response, array $data, int $statusCode = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    /**
     * Writes a standard JSON error response.
     *
     * @param Response $response   The outgoing response.
     * @param int $statusCode The HTTP status code.
     * @param string $error A short error label.
     * @param string $message A human-readable error description.
     * @return Response The JSON error response.
     */
    protected function prepareJsonErrorResponse(Response $response, int $statusCode, string $error, string $message): Response
    {
        return $this->prepareJsonResponse($response, [
            'error'   => $error,
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Extracts a required string field from parsed body data.
     *
     * Returns null when the field is missing or not a string, which
     * allows the caller to return a 400 error.
     *
     * @param array<string, mixed>|null $body      The parsed request body.
     * @param string                    $fieldName The field to extract.
     * @return string|null The trimmed string value or null.
     */
    protected function extractRequiredStringFromBodyData(?array $body, string $fieldName): ?string
    {
        if ($body === null) {
            return null;
        }

        if (array_key_exists($fieldName, $body) === false) {
            return null;
        }

        $value = $body[$fieldName];
        if (is_string($value) === false) {
            return null;
        }

        return trim($value);
    }

    /**
     * Extracts an optional string field from parsed body data.
     *
     * Returns null when the field is absent, not a string, or empty
     * after trimming.
     *
     * @param array<string, mixed>|null $body      The parsed request body.
     * @param string                    $fieldName The field to extract.
     * @return string|null The trimmed value or null.
     */
    protected function extractOptionalStringFromBodyData(?array $body, string $fieldName): ?string
    {
        $value = $this->extractRequiredStringFromBodyData($body, $fieldName);

        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
