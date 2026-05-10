<?php

declare(strict_types=1);

/**
 * RESTful API controller for permission-set user group resources.
 *
 * Manages the many-to-many relationship between PermissionSets and
 * UserGroups. Uses composite route parameters for single-record operations.
 */

namespace Lampfire\Controllers\Api;

use App\Middleware\AuthMiddleware;
use Lampfire\Records\PermissionSetUserGroupRecord;
use Lampfire\Services\PermissionSetService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractRestController;
use Lampfire\Services\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PermissionSetUserGroupController extends AbstractRestController
{
    protected string $routePrefix = '/api/permission-set-user-groups';
    protected string $idPattern = '/{userGroupId}/{permissionSetId}';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * @var PermissionSetService The permission set business logic service.
     */
    private PermissionSetService $permissionSetService;
    private PermissionSetUserGroupRecord $permissionSetUserGroupRecord;
    private UserService $userService;

    /**
     * Creates the permission set user group controller.
     *
     * @param PermissionSetService $permissionSetService The permission set service.
     * @param PermissionSetUserGroupRecord $permissionSetUserGroupRecord The permission set user group record gateway.
     * @param UserService $userService The user service.
     */
    public function __construct(
        PermissionSetService $permissionSetService,
        PermissionSetUserGroupRecord $permissionSetUserGroupRecord,
        UserService $userService
    )
    {
        $this->permissionSetService = $permissionSetService;
        $this->permissionSetUserGroupRecord = $permissionSetUserGroupRecord;
        $this->userService = $userService;
    }

    /**
     * GET /permission-set-user-groups - Returns memberships filtered by set or group.
     *
     * Requires either a permission_set_id or user_group_id query parameter.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response containing the membership list.
     */
    public function get(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_SET_USER_GROUP_READ') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to read permission set user groups.');
        }

        $params = $request->getQueryParams();
        $permissionSetId = $params['permission_set_id'] ?? null;
        $userGroupId = $params['user_group_id'] ?? null;

        if (is_string($permissionSetId) && $this->isValidUuid($permissionSetId)) {
            $members = $this->permissionSetUserGroupRecord->findBySetId($permissionSetId);

            return $this->prepareJsonResponse($response, ['data' => $members]);
        }

        if (is_string($userGroupId) && $this->isValidUuid($userGroupId)) {
            $members = $this->permissionSetUserGroupRecord->findByGroupId($userGroupId);

            return $this->prepareJsonResponse($response, ['data' => $members]);
        }

        return $this->prepareJsonErrorResponse(
            $response,
            400,
            'Bad Request',
            'A valid permission_set_id or user_group_id query parameter is required.'
        );
    }

    /**
     * GET /permission-set-user-groups/{userGroupId}/{permissionSetId} - Returns a single membership.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response with the membership or an error.
     */
    public function getById(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_SET_USER_GROUP_READ') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to read permission set user groups.');
        }

        $userGroupId = $this->routeArgument($request, 'userGroupId');
        $permissionSetId = $this->routeArgument($request, 'permissionSetId');

        if ($this->isValidUuid($userGroupId) === false || $this->isValidUuid($permissionSetId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $record = $this->permissionSetUserGroupRecord->getByPrimaryKey($userGroupId, $permissionSetId);

        if ($record === null) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested membership does not exist.');
        }

        $member = [
            'user_group_id' => $record->getUserGroupId(),
            'permission_set_id' => $record->getPermissionSetId(),
            'access_granted' => $record->getAccessGranted(),
            'access_expiry' => $record->getAccessExpiry(),
            'has_access' => $record->getHasAccess(),
            'notes' => $record->getNotes(),
            'created_at' => $record->getCreatedAt(),
            'updated_at' => $record->getUpdatedAt(),
        ];

        return $this->prepareJsonResponse($response, ['data' => $member]);
    }

    /**
     * POST /permission-set-user-groups - Adds a group to a permission set.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 201 response with the new membership or a 400 error.
     */
    public function post(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_SET_USER_GROUP_CREATE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to create permission set user groups.');
        }

        $body = $request->getParsedBody();
        $userGroupId = $this->extractRequiredStringFromBodyData($body, 'user_group_id');
        $permissionSetId = $this->extractRequiredStringFromBodyData($body, 'permission_set_id');
        $accessGranted = $this->extractRequiredStringFromBodyData($body, 'access_granted');
        $accessExpiry = $this->extractRequiredStringFromBodyData($body, 'access_expiry');
        $hasAccess = is_array($body) && array_key_exists('has_access', $body) ? (bool) $body['has_access'] : false;
        $notes = $this->extractOptionalStringFromBodyData($body, 'notes');

        if ($userGroupId === null || $permissionSetId === null || $accessGranted === null || $accessExpiry === null) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'The user_group_id, permission_set_id, access_granted, and access_expiry fields are required.'
            );
        }

        try {
            $member = $this->permissionSetService->addGroupToSet(
                $userGroupId,
                $permissionSetId,
                $accessGranted,
                $accessExpiry,
                $hasAccess,
                $notes
            );

            return $this->prepareJsonResponse($response, ['data' => $member], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * PUT /permission-set-user-groups/{userGroupId}/{permissionSetId} - Updates a group membership.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 200 response with the updated membership or an error.
     */
    public function put(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_SET_USER_GROUP_UPDATE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to update permission set user groups.');
        }

        $userGroupId = $this->routeArgument($request, 'userGroupId');
        $permissionSetId = $this->routeArgument($request, 'permissionSetId');

        if ($this->isValidUuid($userGroupId) === false || $this->isValidUuid($permissionSetId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $body = $request->getParsedBody();
        $accessGranted = $this->extractRequiredStringFromBodyData($body, 'access_granted');
        $accessExpiry = $this->extractRequiredStringFromBodyData($body, 'access_expiry');
        $hasAccess = is_array($body) && array_key_exists('has_access', $body) ? (bool) $body['has_access'] : false;
        $notes = $this->extractOptionalStringFromBodyData($body, 'notes');

        if ($accessGranted === null || $accessExpiry === null) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'The access_granted and access_expiry fields are required.'
            );
        }

        try {
            $member = $this->permissionSetService->updateSetGroupMember(
                $userGroupId,
                $permissionSetId,
                $accessGranted,
                $accessExpiry,
                $hasAccess,
                $notes
            );

            if ($member === null) {
                return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested membership does not exist.');
            }

            return $this->prepareJsonResponse($response, ['data' => $member]);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * DELETE /permission-set-user-groups/{userGroupId}/{permissionSetId} - Removes a group from a set.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 204 response on success or an error.
     */
    public function delete(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_SET_USER_GROUP_DELETE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to delete permission set user groups.');
        }

        $userGroupId = $this->routeArgument($request, 'userGroupId');
        $permissionSetId = $this->routeArgument($request, 'permissionSetId');

        if ($this->isValidUuid($userGroupId) === false || $this->isValidUuid($permissionSetId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $deleted = $this->permissionSetService->removeGroupFromSet($userGroupId, $permissionSetId);

        if ($deleted === false) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested membership does not exist.');
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
