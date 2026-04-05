<?php

declare(strict_types=1);

/**
 * RESTful API controller for user resources.
 *
 * All methods return JSON responses with appropriate HTTP status codes.
 * This controller does not contain business logic; it delegates all
 * operations to the UserService.
 */

namespace Lampfire\Controllers\Api;

use App\Middleware\AuthMiddleware;
use Lampfire\Services\UserService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractRestController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController extends AbstractRestController
{
    protected string $routePrefix = '/api/users';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * @var UserService The user business logic service.
     */
    private UserService $userService;

    /**
     * Creates the API user controller.
     *
     * @param UserService $userService The user business logic service.
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * GET /users - Returns a JSON array of all users.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response containing the user list.
     */
    public function get(Request $request, Response $response): Response
    {
        $authUserId   = (string) $request->getAttribute('auth_user_id', '');
        $authUsername = (string) $request->getAttribute('auth_username', '');

        if ($this->userService->isAuthorizedForUserWrite($authUserId, $authUsername, 'ADMIN_PERMISSION_USER_READ') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to read users.');
        }

        $users = $this->userService->getAllUsers();

        return $this->prepareJsonResponse($response, ['data' => $users]);
    }

    /**
     * GET /users/{id} - Returns a single user by identifier.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A JSON response with the user or an error.
     */
    public function getById(Request $request, Response $response): Response
    {
        $authUserId   = (string) $request->getAttribute('auth_user_id', '');
        $authUsername = (string) $request->getAttribute('auth_username', '');

        if ($this->userService->isAuthorizedForUserWrite($authUserId, $authUsername, 'ADMIN_PERMISSION_USER_READ') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to read users.');
        }

        $userId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($userId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $user = $this->userService->getUserById($userId);

        if ($user === null) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested user does not exist.');
        }

        return $this->prepareJsonResponse($response, ['data' => $user]);
    }

    /**
     * POST /users - Creates a new user from the JSON request body.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 201 response with the new user or a 400 error.
     */
    public function post(Request $request, Response $response): Response
    {
        $authUserId   = (string) $request->getAttribute('auth_user_id', '');
        $authUsername = (string) $request->getAttribute('auth_username', '');

        if ($this->userService->isAuthorizedForUserWrite($authUserId, $authUsername, 'ADMIN_PERMISSION_USER_CREATE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to create users.');
        }

        $body      = $request->getParsedBody();
        $username  = $this->extractRequiredStringFromBodyData($body, 'username');
        $password  = $this->extractRequiredStringFromBodyData($body, 'password');
        $email     = $this->extractRequiredStringFromBodyData($body, 'email_address');
        $firstName = $this->extractOptionalStringFromBodyData($body, 'first_name');
        $lastName  = $this->extractOptionalStringFromBodyData($body, 'last_name');

        if ($username === null || $password === null || $email === null) {
            return $this->prepareJsonErrorResponse(
                $response,
                400,
                'Bad Request',
                'The username, password, and email_address fields are required strings.'
            );
        }

        try {
            $user = $this->userService->createUser($username, $password, $email, $firstName, $lastName);

            return $this->prepareJsonResponse($response, ['data' => $user], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * PUT /users/{id} - Updates an existing user from the JSON request body.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 200 response with the updated user or an error.
     */
    public function put(Request $request, Response $response): Response
    {
        $authUserId   = (string) $request->getAttribute('auth_user_id', '');
        $authUsername = (string) $request->getAttribute('auth_username', '');

        if ($this->userService->isAuthorizedForUserWrite($authUserId, $authUsername, 'ADMIN_PERMISSION_USER_UPDATE') === false) {
            return $this->prepareJsonErrorResponse($response, 403, 'Forbidden', 'You are not authorized to update users.');
        }

        $userId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($userId) === false) {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $body      = $request->getParsedBody();
        $email     = $this->extractRequiredStringFromBodyData($body, 'email_address');
        $firstName = $this->extractOptionalStringFromBodyData($body, 'first_name');
        $lastName  = $this->extractOptionalStringFromBodyData($body, 'last_name');
        $enabledRaw = $body['enabled'] ?? null;

        if ($email === null || $email === '') {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The email_address field is required.');
        }

        if ($enabledRaw !== null && !is_bool($enabledRaw) && $enabledRaw !== '1' && $enabledRaw !== '0' && $enabledRaw !== 'true' && $enabledRaw !== 'false') {
            return $this->prepareJsonErrorResponse($response, 400, 'Bad Request', 'The enabled field must be a boolean value.');
        }

        $existingUser = $this->userService->getUserById($userId);
        if ($existingUser === null) {
            return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested user does not exist.');
        }

        if ($enabledRaw === null) {
            $enabled = (bool) $existingUser['enabled'];
        } elseif (is_bool($enabledRaw)) {
            $enabled = $enabledRaw;
        } else {
            $enabled = $enabledRaw === '1' || $enabledRaw === 'true';
        }

        if ($userId === $authUserId && $enabled === false) {
            return $this->prepareJsonErrorResponse($response, 422, 'Unprocessable Entity', 'You cannot disable your own account.');
        }

        try {
            $user = $this->userService->updateUser($userId, $email, $enabled, $firstName, $lastName);

            if ($user === null) {
                return $this->prepareJsonErrorResponse($response, 404, 'Not Found', 'The requested user does not exist.');
            }

            return $this->prepareJsonResponse($response, ['data' => $user]);
        } catch (InvalidArgumentException $exception) {
            return $this->prepareJsonErrorResponse($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * A hard check against deleting users. Users can be disabled. Try that!
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function delete(Request $request, Response $response): Response
    {
        return $this->prepareJsonErrorResponse($response, 405, 'Method Not Allowed', 'Deleting users is not supported by this API.');
    }

}
