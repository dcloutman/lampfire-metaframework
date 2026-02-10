<?php

declare(strict_types=1);

/**
 * RESTful API controller for permission-set group membership resources.
 *
 * Manages the many-to-many relationship between PermissionSets and
 * UserGroups. Uses composite route parameters for single-record operations.
 */

namespace App\Controllers\Api;

use App\Middleware\AuthMiddleware;
use App\Services\PermissionSetService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractRestController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PermissionSetGroupMemberController extends AbstractRestController
{
    protected string $routePrefix = '/api/permission-set-group-members';
    protected string $idPattern = '/{groupId}/{setId}';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * @var PermissionSetService The permission set business logic service.
     */
    private PermissionSetService $permissionSetService;

    /**
     * Creates the permission set group member controller.
     *
     * @param PermissionSetService $permissionSetService The permission set service.
     */
    public function __construct(PermissionSetService $permissionSetService)
    {
        $this->permissionSetService = $permissionSetService;
    }

    /**
     * GET /permission-set-group-members - Returns memberships filtered by set or group.
     *
     * Requires either a permission_set_id or user_group_id query parameter.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response containing the membership list.
     */
    public function get(Request $request, Response $response): Response
    {
        $params      = $request->getQueryParams();
        $setId       = $params['permission_set_id'] ?? null;
        $userGroupId = $params['user_group_id'] ?? null;

        if (is_string($setId) && $this->isValidUuid($setId)) {
            $members = $this->permissionSetService->getGroupMembersBySetId($setId);

            return $this->jsonResponse($response, ['data' => $members]);
        }

        if (is_string($userGroupId) && $this->isValidUuid($userGroupId)) {
            // The service does not currently expose a findByGroupId method, but
            // the gateway does. For now, filter via the set id approach.
            // This placeholder returns an error until a dedicated service
            // method is added.
            return $this->jsonError(
                $response,
                400,
                'Bad Request',
                'Filtering by user_group_id is not yet supported. Use permission_set_id instead.'
            );
        }

        return $this->jsonError(
            $response,
            400,
            'Bad Request',
            'A valid permission_set_id query parameter is required.'
        );
    }

    /**
     * GET /permission-set-group-members/{groupId}/{setId} - Returns a single membership.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response with the membership or an error.
     */
    public function getById(Request $request, Response $response): Response
    {
        $groupId = $this->routeArgument($request, 'groupId');
        $setId   = $this->routeArgument($request, 'setId');

        if ($this->isValidUuid($groupId) === false || $this->isValidUuid($setId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $member = $this->permissionSetService->getSetGroupMember($groupId, $setId);

        if ($member === null) {
            return $this->jsonError($response, 404, 'Not Found', 'The requested membership does not exist.');
        }

        return $this->jsonResponse($response, ['data' => $member]);
    }

    /**
     * POST /permission-set-group-members - Adds a group to a permission set.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 201 response with the new membership or a 400 error.
     */
    public function post(Request $request, Response $response): Response
    {
        $body          = $request->getParsedBody();
        $groupId       = $this->extractString($body, 'user_group_id');
        $setId         = $this->extractString($body, 'permission_set_id');
        $accessGranted = $this->extractString($body, 'access_granted');
        $accessExpiry  = $this->extractString($body, 'access_expiry');
        $hasAccess     = is_array($body) && array_key_exists('has_access', $body) ? (bool) $body['has_access'] : false;
        $notes         = $this->extractOptionalString($body, 'notes');

        if ($groupId === null || $setId === null || $accessGranted === null || $accessExpiry === null) {
            return $this->jsonError(
                $response,
                400,
                'Bad Request',
                'The user_group_id, permission_set_id, access_granted, and access_expiry fields are required.'
            );
        }

        try {
            $member = $this->permissionSetService->addGroupToSet(
                $groupId,
                $setId,
                $accessGranted,
                $accessExpiry,
                $hasAccess,
                $notes
            );

            return $this->jsonResponse($response, ['data' => $member], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * PUT /permission-set-group-members/{groupId}/{setId} - Updates a group membership.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 200 response with the updated membership or an error.
     */
    public function put(Request $request, Response $response): Response
    {
        $groupId = $this->routeArgument($request, 'groupId');
        $setId   = $this->routeArgument($request, 'setId');

        if ($this->isValidUuid($groupId) === false || $this->isValidUuid($setId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $body          = $request->getParsedBody();
        $accessGranted = $this->extractString($body, 'access_granted');
        $accessExpiry  = $this->extractString($body, 'access_expiry');
        $hasAccess     = is_array($body) && array_key_exists('has_access', $body) ? (bool) $body['has_access'] : false;
        $notes         = $this->extractOptionalString($body, 'notes');

        if ($accessGranted === null || $accessExpiry === null) {
            return $this->jsonError(
                $response,
                400,
                'Bad Request',
                'The access_granted and access_expiry fields are required.'
            );
        }

        try {
            $member = $this->permissionSetService->updateSetGroupMember(
                $groupId,
                $setId,
                $accessGranted,
                $accessExpiry,
                $hasAccess,
                $notes
            );

            if ($member === null) {
                return $this->jsonError($response, 404, 'Not Found', 'The requested membership does not exist.');
            }

            return $this->jsonResponse($response, ['data' => $member]);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * DELETE /permission-set-group-members/{groupId}/{setId} - Removes a group from a set.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 204 response on success or an error.
     */
    public function delete(Request $request, Response $response): Response
    {
        $groupId = $this->routeArgument($request, 'groupId');
        $setId   = $this->routeArgument($request, 'setId');

        if ($this->isValidUuid($groupId) === false || $this->isValidUuid($setId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $deleted = $this->permissionSetService->removeGroupFromSet($groupId, $setId);

        if ($deleted === false) {
            return $this->jsonError($response, 404, 'Not Found', 'The requested membership does not exist.');
        }

        return $response->withStatus(204);
    }
}
