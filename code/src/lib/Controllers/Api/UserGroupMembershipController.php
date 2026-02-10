<?php

declare(strict_types=1);

/**
 * RESTful API controller for user group membership resources.
 *
 * Manages the many-to-many relationship between Users and UserGroups.
 * Uses composite route parameters for single-record operations.
 */

namespace App\Controllers\Api;

use App\Middleware\AuthMiddleware;
use App\Services\UserGroupService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractRestController;
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
     * Creates the user group membership controller.
     *
     * @param UserGroupService $userGroupService The user group service.
     */
    public function __construct(UserGroupService $userGroupService)
    {
        $this->userGroupService = $userGroupService;
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
        $params      = $request->getQueryParams();
        $userGroupId = $params['user_group_id'] ?? null;
        $userId      = $params['user_id'] ?? null;

        if (is_string($userGroupId) && $this->isValidUuid($userGroupId)) {
            $memberships = $this->userGroupService->getMembersByGroupId($userGroupId);

            return $this->jsonResponse($response, ['data' => $memberships]);
        }

        if (is_string($userId) && $this->isValidUuid($userId)) {
            $memberships = $this->userGroupService->getMembershipsByUserId($userId);

            return $this->jsonResponse($response, ['data' => $memberships]);
        }

        return $this->jsonError(
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
        $userId      = $this->routeArgument($request, 'userId');
        $userGroupId = $this->routeArgument($request, 'userGroupId');

        if ($this->isValidUuid($userId) === false || $this->isValidUuid($userGroupId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $membership = $this->userGroupService->getMembership($userId, $userGroupId);

        if ($membership === null) {
            return $this->jsonError($response, 404, 'Not Found', 'The requested membership does not exist.');
        }

        return $this->jsonResponse($response, ['data' => $membership]);
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
        $body          = $request->getParsedBody();
        $userId        = $this->extractString($body, 'user_id');
        $userGroupId   = $this->extractString($body, 'user_group_id');
        $accessGranted = $this->extractString($body, 'access_granted');
        $accessExpiry  = $this->extractString($body, 'access_expiry');
        $hasAccess     = is_array($body) && array_key_exists('has_access', $body) ? (bool) $body['has_access'] : false;

        if ($userId === null || $userGroupId === null || $accessGranted === null || $accessExpiry === null) {
            return $this->jsonError(
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

            return $this->jsonResponse($response, ['data' => $membership], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, 400, 'Validation Error', $exception->getMessage());
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
        $userId      = $this->routeArgument($request, 'userId');
        $userGroupId = $this->routeArgument($request, 'userGroupId');

        if ($this->isValidUuid($userId) === false || $this->isValidUuid($userGroupId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $body          = $request->getParsedBody();
        $accessGranted = $this->extractString($body, 'access_granted');
        $accessExpiry  = $this->extractString($body, 'access_expiry');
        $hasAccess     = is_array($body) && array_key_exists('has_access', $body) ? (bool) $body['has_access'] : false;

        if ($accessGranted === null || $accessExpiry === null) {
            return $this->jsonError(
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
                return $this->jsonError($response, 404, 'Not Found', 'The requested membership does not exist.');
            }

            return $this->jsonResponse($response, ['data' => $membership]);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * DELETE /user-group-memberships/{userId}/{userGroupId} - Deletes a membership.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 204 response on success or an error.
     */
    public function delete(Request $request, Response $response): Response
    {
        $userId      = $this->routeArgument($request, 'userId');
        $userGroupId = $this->routeArgument($request, 'userGroupId');

        if ($this->isValidUuid($userId) === false || $this->isValidUuid($userGroupId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $deleted = $this->userGroupService->removeMember($userId, $userGroupId);

        if ($deleted === false) {
            return $this->jsonError($response, 404, 'Not Found', 'The requested membership does not exist.');
        }

        return $response->withStatus(204);
    }
}
