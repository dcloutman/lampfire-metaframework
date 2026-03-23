<?php

declare(strict_types=1);

/**
 * RESTful API controller for user group resources.
 *
 * All methods return JSON responses with appropriate HTTP status codes.
 * This controller delegates all operations to the UserGroupService.
 */

namespace Lampfire\Controllers\Api;

use App\Middleware\AuthMiddleware;
use App\Services\UserGroupService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractRestController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserGroupController extends AbstractRestController
{
    protected string $routePrefix = '/api/user-groups';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * @var UserGroupService The user group business logic service.
     */
    private UserGroupService $userGroupService;

    /**
     * Creates the user group controller.
     *
     * @param UserGroupService $userGroupService The user group service.
     */
    public function __construct(UserGroupService $userGroupService)
    {
        $this->userGroupService = $userGroupService;
    }

    /**
     * GET /user-groups - Returns a JSON array of all user groups.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response containing the group list.
     */
    public function get(Request $request, Response $response): Response
    {
        $groups = $this->userGroupService->getAllGroups();

        return $this->prepareJsonResponse($response, ['data' => $groups]);
    }

    /**
     * GET /user-groups/{id} - Returns a single user group by identifier.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response with the group or an error.
     */
    public function getById(Request $request, Response $response): Response
    {
        $groupId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($groupId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $group = $this->userGroupService->getGroupById($groupId);

        if ($group === null) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested user group does not exist.');
        }

        return $this->prepareJsonResponse($response, ['data' => $group]);
    }

    /**
     * POST /user-groups - Creates a new user group from the JSON request body.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 201 response with the new group or a 400 error.
     */
    public function post(Request $request, Response $response): Response
    {
        $body        = $request->getParsedBody();
        $groupName   = $this->extractRequiredStringFromBodyData($body, 'group_name');
        $description = $this->extractOptionalStringFromBodyData($body, 'description');

        if ($groupName === null) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The group_name field is required.');
        }

        try {
            $group = $this->userGroupService->createGroup($groupName, $description);

            return $this->prepareJsonResponse($response, ['data' => $group], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * PUT /user-groups/{id} - Updates an existing user group.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 200 response with the updated group or an error.
     */
    public function put(Request $request, Response $response): Response
    {
        $groupId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($groupId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $body        = $request->getParsedBody();
        $groupName   = $this->extractRequiredStringFromBodyData($body, 'group_name');
        $description = $this->extractOptionalStringFromBodyData($body, 'description');

        if ($groupName === null) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The group_name field is required.');
        }

        try {
            $group = $this->userGroupService->updateGroup($groupId, $groupName, $description);

            if ($group === null) {
                return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested user group does not exist.');
            }

            return $this->prepareJsonResponse($response, ['data' => $group]);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * DELETE /user-groups/{id} - Deletes a user group by identifier.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 204 response on success or an error.
     */
    public function delete(Request $request, Response $response): Response
    {
        $groupId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($groupId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $deleted = $this->userGroupService->deleteGroup($groupId);

        if ($deleted === false) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested user group does not exist.');
        }

        return $response->withStatus(204);
    }
}
