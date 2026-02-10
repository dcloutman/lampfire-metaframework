<?php

declare(strict_types=1);

/**
 * RESTful API controller for permission resources.
 *
 * All methods return JSON responses with appropriate HTTP status codes.
 * This controller delegates all operations to the PermissionService.
 */

namespace App\Controllers\Api;

use App\Middleware\AuthMiddleware;
use App\Services\PermissionService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractRestController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PermissionController extends AbstractRestController
{
    protected string $routePrefix = '/api/permissions';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * @var PermissionService The permission business logic service.
     */
    private PermissionService $permissionService;

    /**
     * Creates the permission controller.
     *
     * @param PermissionService $permissionService The permission service.
     */
    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * GET /permissions - Returns a JSON array of all permissions.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response containing the permission list.
     */
    public function get(Request $request, Response $response): Response
    {
        $permissions = $this->permissionService->getAllPermissions();

        return $this->jsonResponse($response, ['data' => $permissions]);
    }

    /**
     * GET /permissions/{id} - Returns a single permission by identifier.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response with the permission or an error.
     */
    public function getById(Request $request, Response $response): Response
    {
        $permissionId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($permissionId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $permission = $this->permissionService->getPermissionById($permissionId);

        if ($permission === null) {
            return $this->jsonError($response, 404, 'Not Found', 'The requested permission does not exist.');
        }

        return $this->jsonResponse($response, ['data' => $permission]);
    }

    /**
     * POST /permissions - Creates a new permission from the JSON request body.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 201 response with the new permission or a 400 error.
     */
    public function post(Request $request, Response $response): Response
    {
        $body            = $request->getParsedBody();
        $permissionToken = $this->extractString($body, 'permission_token');
        $permissionTitle = $this->extractString($body, 'permission_title');
        $notes           = $this->extractOptionalString($body, 'notes');

        if ($permissionToken === null || $permissionTitle === null) {
            return $this->jsonError(
                $response,
                400,
                'Bad Request',
                'The permission_token and permission_title fields are required.'
            );
        }

        try {
            $permission = $this->permissionService->createPermission($permissionToken, $permissionTitle, $notes);

            return $this->jsonResponse($response, ['data' => $permission], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * PUT /permissions/{id} - Updates an existing permission.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 200 response with the updated permission or an error.
     */
    public function put(Request $request, Response $response): Response
    {
        $permissionId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($permissionId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $body            = $request->getParsedBody();
        $permissionToken = $this->extractString($body, 'permission_token');
        $permissionTitle = $this->extractString($body, 'permission_title');
        $notes           = $this->extractOptionalString($body, 'notes');

        if ($permissionToken === null || $permissionTitle === null) {
            return $this->jsonError(
                $response,
                400,
                'Bad Request',
                'The permission_token and permission_title fields are required.'
            );
        }

        try {
            $permission = $this->permissionService->updatePermission(
                $permissionId,
                $permissionToken,
                $permissionTitle,
                $notes
            );

            if ($permission === null) {
                return $this->jsonError($response, 404, 'Not Found', 'The requested permission does not exist.');
            }

            return $this->jsonResponse($response, ['data' => $permission]);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * DELETE /permissions/{id} - Deletes a permission by identifier.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 204 response on success or an error.
     */
    public function delete(Request $request, Response $response): Response
    {
        $permissionId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($permissionId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $deleted = $this->permissionService->deletePermission($permissionId);

        if ($deleted === false) {
            return $this->jsonError($response, 404, 'Not Found', 'The requested permission does not exist.');
        }

        return $response->withStatus(204);
    }
}
