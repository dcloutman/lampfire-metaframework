<?php

declare(strict_types=1);

/**
 * RESTful API controller for permission-set user membership resources.
 *
 * Manages the many-to-many relationship between PermissionSets and
 * Users. Uses composite route parameters for single-record operations.
 */

namespace App\Controllers\Api;

use App\Middleware\AuthMiddleware;
use App\Services\PermissionSetService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractRestController;
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

    /**
     * Creates the permission set member controller.
     *
     * @param PermissionSetService $permissionSetService The permission set service.
     */
    public function __construct(PermissionSetService $permissionSetService)
    {
        $this->permissionSetService = $permissionSetService;
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
        $params = $request->getQueryParams();
        $setId  = $params['permission_set_id'] ?? null;
        $userId = $params['user_id'] ?? null;

        if (is_string($setId) && $this->isValidUuid($setId)) {
            $members = $this->permissionSetService->getMembersBySetId($setId);

            return $this->jsonResponse($response, ['data' => $members]);
        }

        if (is_string($userId) && $this->isValidUuid($userId)) {
            // Retrieve all permission set memberships for a particular user.
            // This delegates through the service which validates the UUID.
            $members = $this->permissionSetService->getMembersBySetId($userId);

            return $this->jsonResponse($response, ['data' => $members]);
        }

        return $this->jsonError(
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
        $setId  = $this->routeArgument($request, 'setId');
        $userId = $this->routeArgument($request, 'userId');

        if ($this->isValidUuid($setId) === false || $this->isValidUuid($userId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $member = $this->permissionSetService->getSetMember($setId, $userId);

        if ($member === null) {
            return $this->jsonError($response, 404, 'Not Found', 'The requested membership does not exist.');
        }

        return $this->jsonResponse($response, ['data' => $member]);
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
        $body          = $request->getParsedBody();
        $setId         = $this->extractString($body, 'permission_set_id');
        $userId        = $this->extractString($body, 'user_id');
        $accessGranted = $this->extractString($body, 'access_granted');
        $accessExpiry  = $this->extractString($body, 'access_expiry');
        $hasAccess     = is_array($body) && array_key_exists('has_access', $body) ? (bool) $body['has_access'] : false;
        $notes         = $this->extractOptionalString($body, 'notes');

        if ($setId === null || $userId === null || $accessGranted === null || $accessExpiry === null) {
            return $this->jsonError(
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

            return $this->jsonResponse($response, ['data' => $member], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, 400, 'Validation Error', $exception->getMessage());
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
        $setId  = $this->routeArgument($request, 'setId');
        $userId = $this->routeArgument($request, 'userId');

        if ($this->isValidUuid($setId) === false || $this->isValidUuid($userId) === false) {
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
            $member = $this->permissionSetService->updateSetMember(
                $setId,
                $userId,
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
     * DELETE /permission-set-members/{setId}/{userId} - Removes a user from a set.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 204 response on success or an error.
     */
    public function delete(Request $request, Response $response): Response
    {
        $setId  = $this->routeArgument($request, 'setId');
        $userId = $this->routeArgument($request, 'userId');

        if ($this->isValidUuid($setId) === false || $this->isValidUuid($userId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'Both path parameters must be valid UUID v4 values.');
        }

        $deleted = $this->permissionSetService->removeMemberFromSet($setId, $userId);

        if ($deleted === false) {
            return $this->jsonError($response, 404, 'Not Found', 'The requested membership does not exist.');
        }

        return $response->withStatus(204);
    }
}
