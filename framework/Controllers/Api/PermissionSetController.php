<?php

declare(strict_types=1);

/**
 * RESTful API controller for permission set resources.
 *
 * All methods return JSON responses with appropriate HTTP status codes.
 * This controller delegates all operations to the PermissionSetService.
 */

namespace Lampfire\Controllers\Api;

use App\Middleware\AuthMiddleware;
use Lampfire\Services\PermissionSetService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractRestController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PermissionSetController extends AbstractRestController
{
    protected string $routePrefix = '/api/permission-sets';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * @var PermissionSetService The permission set business logic service.
     */
    private PermissionSetService $permissionSetService;

    /**
     * Creates the permission set controller.
     *
     * @param PermissionSetService $permissionSetService The permission set service.
     */
    public function __construct(PermissionSetService $permissionSetService)
    {
        $this->permissionSetService = $permissionSetService;
    }

    /**
     * GET /permission-sets - Returns a JSON array of all permission sets.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response containing the set list.
     */
    public function get(Request $request, Response $response): Response
    {
        $sets = $this->permissionSetService->getAllSets();

        return $this->prepareJsonResponse($response, ['data' => $sets]);
    }

    /**
     * GET /permission-sets/{id} - Returns a single permission set by identifier.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response with the set or an error.
     */
    public function getById(Request $request, Response $response): Response
    {
        $setId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($setId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $set = $this->permissionSetService->getSetById($setId);

        if ($set === null) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested permission set does not exist.');
        }

        return $this->prepareJsonResponse($response, ['data' => $set]);
    }

    /**
     * POST /permission-sets - Creates a new permission set from the JSON body.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 201 response with the new set or a 400 error.
     */
    public function post(Request $request, Response $response): Response
    {
        $body  = $request->getParsedBody();
        $token = $this->extractRequiredStringFromBodyData($body, 'permission_set_token');
        $title = $this->extractRequiredStringFromBodyData($body, 'title');
        $notes = $this->extractOptionalStringFromBodyData($body, 'notes');

        if ($token === null || $title === null) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'The permission_set_token and title fields are required.'
            );
        }

        try {
            $set = $this->permissionSetService->createSet($token, $title, $notes);

            return $this->prepareJsonResponse($response, ['data' => $set], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * PUT /permission-sets/{id} - Updates an existing permission set.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 200 response with the updated set or an error.
     */
    public function put(Request $request, Response $response): Response
    {
        $setId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($setId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $body  = $request->getParsedBody();
        $token = $this->extractRequiredStringFromBodyData($body, 'permission_set_token');
        $title = $this->extractRequiredStringFromBodyData($body, 'title');
        $notes = $this->extractOptionalStringFromBodyData($body, 'notes');

        if ($token === null || $title === null) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'The permission_set_token and title fields are required.'
            );
        }

        try {
            $set = $this->permissionSetService->updateSet($setId, $token, $title, $notes);

            if ($set === null) {
                return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested permission set does not exist.');
            }

            return $this->prepareJsonResponse($response, ['data' => $set]);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * DELETE /permission-sets/{id} - Deletes a permission set by identifier.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 204 response on success or an error.
     */
    public function delete(Request $request, Response $response): Response
    {
        $setId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($setId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $deleted = $this->permissionSetService->deleteSet($setId);

        if ($deleted === false) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested permission set does not exist.');
        }

        return $response->withStatus(204);
    }
}
