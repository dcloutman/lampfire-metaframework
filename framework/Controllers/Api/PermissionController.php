<?php

declare(strict_types=1);

/**
 * RESTful API controller for permission resources.
 *
 * All methods return JSON responses with appropriate HTTP status codes.
 * This controller delegates all operations to the PermissionService.
 */

namespace Lampfire\Controllers\Api;

use App\Middleware\AuthMiddleware;
use App\Services\PermissionService;
use Lampfire\Services\UserService;
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
     * @var UserService Service used to authorize admin API actions.
     */
    private UserService $userService;

    /**
     * Creates the permission controller.
     *
     * @param PermissionService $permissionService The permission service.
     * @param UserService $userService The user service.
     */
    public function __construct(PermissionService $permissionService, UserService $userService)
    {
        $this->permissionService = $permissionService;
        $this->userService = $userService;
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
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_READ') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to read permissions.');
        }

        $permissions = $this->permissionService->getAllPermissions();

        return $this->prepareJsonResponse($response, ['data' => $permissions]);
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
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_READ') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to read permissions.');
        }

        $permissionId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($permissionId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $permission = $this->permissionService->getPermissionById($permissionId);

        if ($permission === null) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested permission does not exist.');
        }

        return $this->prepareJsonResponse($response, ['data' => $permission]);
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
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_CREATE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to create permissions.');
        }

        $body = $request->getParsedBody();
        $permissionToken = $this->extractRequiredStringFromBodyData($body, 'permission_token');
        $permissionTitle = $this->extractRequiredStringFromBodyData($body, 'permission_title');
        $notes = $this->extractOptionalStringFromBodyData($body, 'notes');

        if ($permissionToken === null || $permissionTitle === null) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'The permission_token and permission_title fields are required.'
            );
        }

        try {
            $permission = $this->permissionService->createPermission($permissionToken, $permissionTitle, $notes);

            return $this->prepareJsonResponse($response, ['data' => $permission], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
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
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_UPDATE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to update permissions.');
        }

        $permissionId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($permissionId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        if ($this->permissionService->isAdministrativePermissionId($permissionId)) {
            return $this->prepareJsonErrorResponse(
                $response,
                403,
                'Forbidden',
                'Framework administrative permissions are immutable and cannot be modified.'
            );
        }

        $body = $request->getParsedBody();
        $permissionToken = $this->extractRequiredStringFromBodyData($body, 'permission_token');
        $permissionTitle = $this->extractRequiredStringFromBodyData($body, 'permission_title');
        $notes = $this->extractOptionalStringFromBodyData($body, 'notes');

        if ($permissionToken === null || $permissionTitle === null) {
            return $this->prepareJsonErrorResponse(
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
                return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested permission does not exist.');
            }

            return $this->prepareJsonResponse($response, ['data' => $permission]);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
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
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_DELETE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to delete permissions.');
        }

        $permissionId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($permissionId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        if ($this->permissionService->isAdministrativePermissionId($permissionId)) {
            return $this->prepareJsonErrorResponse(
                $response,
                403,
                'Forbidden',
                'Framework administrative permissions are immutable and cannot be deleted.'
            );
        }

        $deleted = $this->permissionService->deletePermission($permissionId);

        if ($deleted === false) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested permission does not exist.');
        }

        return $response->withStatus(204);
    }

    /**
     * Returns true when the authenticated user has the required admin permission token.
     *
     * @param Request $request The incoming request.
     * @param string $permissionToken The required permission token.
     * @return bool True when authorized.
     */
    private function isAuthorized(Request $request, string $permissionToken): bool
    {
        $authUserId = (string) $request->getAttribute('auth_user_id', '');
        $authUsername = (string) $request->getAttribute('auth_username', '');

        return $this->userService->isAuthorizedForUserWrite($authUserId, $authUsername, $permissionToken);
    }
}
