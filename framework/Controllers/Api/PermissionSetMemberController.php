<?php

declare(strict_types=1);

/**
 * RESTful API controller for permission-set user membership resources.
 *
 * Manages the many-to-many relationship between PermissionSets and
 * Users. Uses composite route parameters for single-record operations.
 */

namespace Lampfire\Controllers\Api;

use App\Middleware\AuthMiddleware;
use Lampfire\Records\PermissionSetMemberRecord;
use Lampfire\Services\PermissionSetService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractRestController;
use Lampfire\Services\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PermissionSetMemberController extends AbstractRestController
{
    protected string $routePrefix = '/api/permission-set-members';
    protected string $idPattern = '/{setId}/{userId}';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * @var PermissionSetService The permission set business logic service.
     */
    private PermissionSetService $permissionSetService;
    private PermissionSetMemberRecord $permissionSetMemberRecord;
    private UserService $userService;

    /**
     * Creates the permission set member controller.
     *
     * @param PermissionSetService $permissionSetService The permission set service.
     * @param PermissionSetMemberRecord $permissionSetMemberRecord The permission set member record gateway.
     * @param UserService $userService The user service.
     */
    public function __construct(
        PermissionSetService $permissionSetService,
        PermissionSetMemberRecord $permissionSetMemberRecord,
        UserService $userService
    )
    {
        $this->permissionSetService = $permissionSetService;
        $this->permissionSetMemberRecord = $permissionSetMemberRecord;
        $this->userService = $userService;
    }

    /**
     * GET /permission-set-members - Returns memberships filtered by set or user.
     *
     * Requires either a permission_set_id or user_id query parameter.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response containing the membership list.
     */
    public function get(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_SET_MEMBER_READ') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to read permission set members.');
        }

        $params = $request->getQueryParams();
        $setId  = $params['permission_set_id'] ?? null;
        $userId = $params['user_id'] ?? null;

        if (is_string($setId) && $this->isValidUuid($setId)) {
            $members = $this->permissionSetMemberRecord->findBySetId($setId);

            return $this->prepareJsonResponse($response, ['data' => $members]);
        }

        if (is_string($userId) && $this->isValidUuid($userId)) {
            $members = $this->permissionSetMemberRecord->findByUserId($userId);

            return $this->prepareJsonResponse($response, ['data' => $members]);
        }

        return $this->prepareJsonErrorResponse(
            $response,
            400,
            'Bad Request',
            'A valid permission_set_id or user_id query parameter is required.'
        );
    }

    /**
     * GET /permission-set-members/{setId}/{userId} - Returns a single membership.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response with the membership or an error.
     */
    public function getById(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_SET_MEMBER_READ') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to read permission set members.');
        }

        $setId  = $this->routeArgument($request, 'setId');
        $userId = $this->routeArgument($request, 'userId');

        if ($this->isValidUuid($setId) === false || $this->isValidUuid($userId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $record = $this->permissionSetMemberRecord->getByPrimaryKey($setId, $userId);

        if ($record === null) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested membership does not exist.');
        }

        $member = [
            'permission_set_id' => $record->getPermissionSetId(),
            'user_id' => $record->getUserId(),
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
     * POST /permission-set-members - Adds a user to a permission set.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 201 response with the new membership or a 400 error.
     */
    public function post(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_SET_MEMBER_CREATE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to create permission set members.');
        }

        $body          = $request->getParsedBody();
        $setId         = $this->extractRequiredStringFromBodyData($body, 'permission_set_id');
        $userId        = $this->extractRequiredStringFromBodyData($body, 'user_id');
        $accessGranted = $this->extractRequiredStringFromBodyData($body, 'access_granted');
        $accessExpiry  = $this->extractRequiredStringFromBodyData($body, 'access_expiry');
        $hasAccess     = is_array($body) && array_key_exists('has_access', $body) ? (bool) $body['has_access'] : false;
        $notes         = $this->extractOptionalStringFromBodyData($body, 'notes');

        if ($setId === null || $userId === null || $accessGranted === null || $accessExpiry === null) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'The permission_set_id, user_id, access_granted, and access_expiry fields are required.'
            );
        }

        try {
            $member = $this->permissionSetService->addMemberToSet(
                $setId,
                $userId,
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
     * PUT /permission-set-members/{setId}/{userId} - Updates a membership.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 200 response with the updated membership or an error.
     */
    public function put(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_SET_MEMBER_UPDATE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to update permission set members.');
        }

        $setId  = $this->routeArgument($request, 'setId');
        $userId = $this->routeArgument($request, 'userId');

        if ($this->isValidUuid($setId) === false || $this->isValidUuid($userId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $body          = $request->getParsedBody();
        $accessGranted = $this->extractRequiredStringFromBodyData($body, 'access_granted');
        $accessExpiry  = $this->extractRequiredStringFromBodyData($body, 'access_expiry');
        $hasAccess     = is_array($body) && array_key_exists('has_access', $body) ? (bool) $body['has_access'] : false;
        $notes         = $this->extractOptionalStringFromBodyData($body, 'notes');

        if ($accessGranted === null || $accessExpiry === null) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'The access_granted and access_expiry fields are required.'
            );
        }

        try {
            $member = $this->permissionSetService->updateSetMember(
                $setId,
                $userId,
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
     * DELETE /permission-set-members/{setId}/{userId} - Removes a user from a set.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 204 response on success or an error.
     */
    public function delete(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_PERMISSION_SET_MEMBER_DELETE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to delete permission set members.');
        }

        $setId  = $this->routeArgument($request, 'setId');
        $userId = $this->routeArgument($request, 'userId');

        if ($this->isValidUuid($setId) === false || $this->isValidUuid($userId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $deleted = $this->permissionSetService->removeMemberFromSet($setId, $userId);

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
