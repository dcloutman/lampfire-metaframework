<?php

declare(strict_types=1);

/**
 * RESTful API controller for user resources.
 *
 * All methods return JSON responses with appropriate HTTP status codes.
 * This controller does not contain business logic; it delegates all
 * operations to the UserService.
 */

namespace App\Controllers\Api;

use App\Middleware\AuthMiddleware;
use App\Services\UserService;
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
        $users = $this->userService->getAllUsers();

        return $this->jsonResponse($response, ['data' => $users]);
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
        $userId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($userId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $user = $this->userService->getUserById($userId);

        if ($user === null) {
            return $this->jsonError($response, 404, 'Not Found', 'The requested user does not exist.');
        }

        return $this->jsonResponse($response, ['data' => $user]);
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
        $body      = $request->getParsedBody();
        $username  = $this->extractString($body, 'username');
        $password  = $this->extractString($body, 'password');
        $email     = $this->extractString($body, 'email_address');
        $firstName = $this->extractOptionalString($body, 'first_name');
        $lastName  = $this->extractOptionalString($body, 'last_name');

        if ($username === null || $password === null || $email === null) {
            return $this->jsonError(
                $response,
                400,
                'Bad Request',
                'The username, password, and email_address fields are required strings.'
            );
        }

        try {
            $user = $this->userService->createUser($username, $password, $email, $firstName, $lastName);

            return $this->jsonResponse($response, ['data' => $user], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, 400, 'Validation Error', $exception->getMessage());
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
        $userId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($userId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        $body      = $request->getParsedBody();
        $username  = $this->extractString($body, 'username');
        $email     = $this->extractString($body, 'email_address');
        $firstName = $this->extractOptionalString($body, 'first_name');
        $lastName  = $this->extractOptionalString($body, 'last_name');

        if ($username === null || $username === '') {
            return $this->jsonError($response, 400, 'Bad Request', 'The username field is required.');
        }

        if ($email === null || $email === '') {
            return $this->jsonError($response, 400, 'Bad Request', 'The email_address field is required.');
        }

        try {
            $user = $this->userService->updateUser($userId, $username, $email, $firstName, $lastName);

            if ($user === null) {
                return $this->jsonError($response, 404, 'Not Found', 'The requested user does not exist.');
            }

            return $this->jsonResponse($response, ['data' => $user]);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, 400, 'Validation Error', $exception->getMessage());
        }
    }

    /**
     * DELETE /users/{id} - Deletes a user by identifier.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A 204 response on success or an error.
     */
    public function delete(Request $request, Response $response): Response
    {
        $userId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($userId) === false) {
            return $this->jsonError($response, 400, 'Bad Request', 'The id parameter must be a valid UUID v4.');
        }

        try {
            $deleted = $this->userService->deleteUser($userId);

            if ($deleted === false) {
                return $this->jsonError($response, 404, 'Not Found', 'The requested user does not exist.');
            }

            return $response->withStatus(204);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonError($response, 400, 'Bad Request', $exception->getMessage());
        }
    }
}
