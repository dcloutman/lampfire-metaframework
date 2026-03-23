<?php

declare(strict_types=1);

/**
 * RESTful API controller for permission-set-to-permission associations.
 *
 * Manages the many-to-many relationship between PermissionSets and
 * Permissions. Uses composite route parameters for single-record operations.
 */

namespace Lampfire\Controllers\Api;

use App\Middleware\AuthMiddleware;
use Lampfire\Services\PermissionSetService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractRestController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PermissionSetPermissionController extends AbstractRestController
{
    protected string $routePrefix = '/api/permission-set-permissions';
    protected string $idPattern = '/{setId}/{permissionId}';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * @var PermissionSetService The permission set business logic service.
     */
    private PermissionSetService $permissionSetService;

    /**
     * Creates the permission-set-permission controller.
     *
     * @param PermissionSetService $permissionSetService The permission set service.
     */
    public function __construct(PermissionSetService $permissionSetService)
    {
        $this->permissionSetService = $permissionSetService;
    }

    /**
     * GET /permission-set-permissions - Returns associations for a permission set.
     *
     * Requires a permission_set_id query parameter.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response containing the association list.
     */
    public function get(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $setId  = $params['permission_set_id'] ?? null;

        if (is_string($setId) === false || $this->isValidUuid($setId) === false) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'A valid permission_set_id query parameter is required.'
            );
        }

        $associations = $this->permissionSetService->getPermissionsBySetId($setId);

        return $this->prepareJsonResponse($response, ['data' => $associations]);
    }

    /**
     * GET /permission-set-permissions/{setId}/{permissionId} - Returns a single association.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response with the association or an error.
     */
    public function getById(Request $request, Response $response): Response
    {
        $setId        = $this->routeArgument($request, 'setId');
        $permissionId = $this->routeArgument($request, 'permissionId');

        if ($this->isValidUuid($setId) === false || $this->isValidUuid($permissionId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $association = $this->permissionSetService->getSetPermission($setId, $permissionId);

        if ($association === null) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested association does not exist.');
        }

        return $this->prepareJsonResponse($response, ['data' => $association]);
    }

    /**
     * POST /permission-set-permissions - Assigns a permission to a set.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 201 response with the new association or a 400 error.
     */
    public function post(Request $request, Response $response): Response
    {
        $body         = $request->getParsedBody();
        $setId        = $this->extractRequiredStringFromBodyData($body, 'permission_set_id');
        $permissionId = $this->extractRequiredStringFromBodyData($body, 'permission_id');
        $notes        = $this->extractOptionalStringFromBodyData($body, 'notes');

        if ($setId === null || $permissionId === null) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'The permission_set_id and permission_id fields are required.'
            );
        }

        try {
            $association = $this->permissionSetService->addPermissionToSet($setId, $permissionId, $notes);

            return $this->prepareJsonResponse($response, ['data' => $association], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * PUT /permission-set-permissions/{setId}/{permissionId} - Updates the association notes.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 200 response with the updated association or an error.
     */
    public function put(Request $request, Response $response): Response
    {
        $setId        = $this->routeArgument($request, 'setId');
        $permissionId = $this->routeArgument($request, 'permissionId');

        if ($this->isValidUuid($setId) === false || $this->isValidUuid($permissionId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $body  = $request->getParsedBody();
        $notes = $this->extractOptionalStringFromBodyData($body, 'notes');

        try {
            $association = $this->permissionSetService->updateSetPermission($setId, $permissionId, $notes);

            if ($association === null) {
                return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested association does not exist.');
            }

            return $this->prepareJsonResponse($response, ['data' => $association]);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * DELETE /permission-set-permissions/{setId}/{permissionId} - Removes a permission from a set.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 204 response on success or an error.
     */
    public function delete(Request $request, Response $response): Response
    {
        $setId        = $this->routeArgument($request, 'setId');
        $permissionId = $this->routeArgument($request, 'permissionId');

        if ($this->isValidUuid($setId) === false || $this->isValidUuid($permissionId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $deleted = $this->permissionSetService->removePermissionFromSet($setId, $permissionId);

        if ($deleted === false) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested association does not exist.');
        }

        return $response->withStatus(204);
    }
}
