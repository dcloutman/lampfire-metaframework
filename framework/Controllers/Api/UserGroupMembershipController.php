<?php

declare(strict_types=1);

/**
 * RESTful API controller for user group membership resources.
 *
 * Manages the many-to-many relationship between Users and UserGroups.
 * Uses composite route parameters for single-record operations.
 */

namespace Lampfire\Controllers\Api;

use App\Middleware\AuthMiddleware;
use App\Services\UserGroupService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractRestController;
use Lampfire\Services\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserGroupMembershipController extends AbstractRestController
{
    protected string $routePrefix = '/api/user-group-memberships';
    protected string $idPattern = '/{userId}/{userGroupId}';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * @var UserGroupService The user group business logic service.
     */
    private UserGroupService $userGroupService;

    /**
     * @var UserService The user business logic service.
     */
    private UserService $userService;

    /**
     * Creates the user group membership controller.
     *
     * @param UserGroupService $userGroupService The user group service.
     * @param UserService      $userService      The user service.
     */
    public function __construct(UserGroupService $userGroupService, UserService $userService)
    {
        $this->userGroupService = $userGroupService;
        $this->userService = $userService;
    }

    /**
     * GET /user-group-memberships - Returns memberships filtered by group or user.
     *
     * Requires either a user_group_id or user_id query parameter.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response containing the membership list.
     */
    public function get(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_USER_GROUP_MEMBERSHIP_READ') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to read user group memberships.');
        }

        $params      = $request->getQueryParams();
        $userGroupId = $params['user_group_id'] ?? null;
        $userId      = $params['user_id'] ?? null;

        if (is_string($userGroupId) && $this->isValidUuid($userGroupId)) {
            $memberships = $this->userGroupService->getMembersByGroupId($userGroupId);

            return $this->prepareJsonResponse($response, ['data' => $memberships]);
        }

        if (is_string($userId) && $this->isValidUuid($userId)) {
            $memberships = $this->userGroupService->getMembershipsByUserId($userId);

            return $this->prepareJsonResponse($response, ['data' => $memberships]);
        }

        return $this->prepareJsonErrorResponse(
            $response,
            400,
            'Bad Request',
            'A valid user_group_id or user_id query parameter is required.'
        );
    }

    /**
     * GET /user-group-memberships/{userId}/{userGroupId} - Returns a single membership.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response with the membership or an error.
     */
    public function getById(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_USER_GROUP_MEMBERSHIP_READ') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to read user group memberships.');
        }

        $userId      = $this->routeArgument($request, 'userId');
        $userGroupId = $this->routeArgument($request, 'userGroupId');

        if ($this->isValidUuid($userId) === false || $this->isValidUuid($userGroupId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $membership = $this->userGroupService->getMembership($userId, $userGroupId);

        if ($membership === null) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested membership does not exist.');
        }

        return $this->prepareJsonResponse($response, ['data' => $membership]);
    }

    /**
     * POST /user-group-memberships - Creates a new membership from the JSON body.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 201 response with the new membership or a 400 error.
     */
    public function post(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_USER_GROUP_MEMBERSHIP_CREATE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to create user group memberships.');
        }

        $body          = $request->getParsedBody();
        $userId        = $this->extractRequiredStringFromBodyData($body, 'user_id');
        $userGroupId   = $this->extractRequiredStringFromBodyData($body, 'user_group_id');
        $accessGranted = $this->extractRequiredStringFromBodyData($body, 'access_granted');
        $accessExpiry  = $this->extractRequiredStringFromBodyData($body, 'access_expiry');
        $hasAccess     = $this->parseHasAccessFromBody($body);

        if ($userId === null || $userGroupId === null || $accessGranted === null || $accessExpiry === null) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'The user_id, user_group_id, access_granted, and access_expiry fields are required.'
            );
        }

        try {
            $membership = $this->userGroupService->addMember(
                $userId,
                $userGroupId,
                $accessGranted,
                $accessExpiry,
                $hasAccess
            );

            return $this->prepareJsonResponse($response, ['data' => $membership], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * PUT /user-group-memberships/{userId}/{userGroupId} - Updates a membership.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 200 response with the updated membership or an error.
     */
    public function put(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_USER_GROUP_MEMBERSHIP_UPDATE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to update user group memberships.');
        }

        $userId      = $this->routeArgument($request, 'userId');
        $userGroupId = $this->routeArgument($request, 'userGroupId');

        if ($this->isValidUuid($userId) === false || $this->isValidUuid($userGroupId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $body          = $request->getParsedBody();
        $accessGranted = $this->extractRequiredStringFromBodyData($body, 'access_granted');
        $accessExpiry  = $this->extractRequiredStringFromBodyData($body, 'access_expiry');
        $hasAccess     = $this->parseHasAccessFromBody($body);

        if ($accessGranted === null || $accessExpiry === null) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'The access_granted and access_expiry fields are required.'
            );
        }

        try {
            $membership = $this->userGroupService->updateMembership(
                $userId,
                $userGroupId,
                $accessGranted,
                $accessExpiry,
                $hasAccess
            );

            if ($membership === null) {
                return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested membership does not exist.');
            }

            return $this->prepareJsonResponse($response, ['data' => $membership]);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * DELETE /user-group-memberships/{userId}/{userGroupId} - Disables a membership.
     *
     * This endpoint performs a non-destructive disable operation.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 204 response on success or an error.
     */
    public function delete(Request $request, Response $response): Response
    {
        if ($this->isAuthorized($request, 'ADMIN_PERMISSION_USER_GROUP_MEMBERSHIP_DELETE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to delete user group memberships.');
        }

        $userId      = $this->routeArgument($request, 'userId');
        $userGroupId = $this->routeArgument($request, 'userGroupId');
        $authUsername = (string) $request->getAttribute('auth_username', '');

        if ($this->isValidUuid($userId) === false || $this->isValidUuid($userGroupId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        if (
            $this->userService->isSuperadminUsername($authUsername) === false
            && $this->userGroupService->isAdministrativeGroupId($userGroupId)
        ) {
            return $this->prepareJsonErrorResponse(
                $response,
                403,
                'Forbidden',
                'Only the superadmin can disable users in the administrative user group.'
            );
        }

        $disabled = $this->userGroupService->removeMember($userId, $userGroupId);

        if ($disabled === false) {
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

    /**
     * Parses the has_access body field into a strict boolean value.
     *
     * Accepts booleans, integers, and common string representations.
     *
     * @param array<string, mixed>|null $body The parsed request body.
     * @return bool The parsed access flag.
     */
    private function parseHasAccessFromBody(?array $body): bool
    {
        if ($body === null || array_key_exists('has_access', $body) === false) {
            return false;
        }

        $rawValue = $body['has_access'];

        if (is_bool($rawValue)) {
            return $rawValue;
        }

        if (is_int($rawValue)) {
            return $rawValue === 1;
        }

        if (is_string($rawValue)) {
            $normalized = strtolower(trim($rawValue));

            return $normalized === '1' || $normalized === 'true' || $normalized === 'yes' || $normalized === 'on';
        }

        return false;
    }
}
