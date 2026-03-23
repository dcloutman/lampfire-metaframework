<?php

declare(strict_types=1);

/**
 * Admin web controller for user management.
 *
 * Handles listing, creating, viewing, editing, deleting, and password
 * resets for user records. All data operations are delegated to the
 * UserService. Templates are rendered server-side with Twig.
 *
 * This controller follows RESTful conventions. The routePrefix property
 * provides the full URI base. HTML forms that need PUT or DELETE use a
 * hidden _METHOD field processed by MethodOverrideMiddleware.
 */

namespace App\Controllers\Admin;

use App\Middleware\AuthMiddleware;
use Lampfire\Services\UserService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractAdminController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class UserAdminController extends AbstractAdminController
{
    protected string $routePrefix = '/admin/users';
    protected array $routeMiddleware = [AuthMiddleware::class];

    /**
     * @var UserService The user business logic service.
     */
    private UserService $userService;

    /**
     * Creates the admin user controller.
     *
     * @param UserService $userService The user business logic service.
     * @param Twig        $twig        The Twig view renderer.
     */
    public function __construct(UserService $userService, Twig $twig)
    {
        parent::__construct($twig);
        $this->userService = $userService;
    }

    /**
     * GET /admin/users - Lists all users in a table view.
     *
     * When the query parameter action=create is present, renders the
     * user creation form instead of the list.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response The rendered page.
     */
    public function get(Request $request, Response $response): Response
    {
        $action = $request->getQueryParams()['action'] ?? 'list';

        if ($action === 'create') {
            return $this->twig->render($response, 'admin/users/create.twig', [
                'pageTitle'  => 'Create User',
                'csrf_token' => $this->getCsrfToken($request),
            ]);
        }

        $users = $this->userService->getAllUsers();

        return $this->twig->render($response, 'admin/users/index.twig', [
            'pageTitle'  => 'Users',
            'users'      => $users,
            'csrf_token' => $this->getCsrfToken($request),
        ]);
    }

    /**
     * GET /admin/users/{id} - Displays a single user record.
     *
     * When the query parameter action=edit is present, renders the
     * edit form instead of the read-only detail view.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response The rendered user detail or edit page.
     */
    public function getById(Request $request, Response $response): Response
    {
        $userId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($userId) === false) {
            return $this->notFound($response, 'The user identifier is not valid.');
        }

        $user = $this->userService->getUserById($userId);

        if ($user === null) {
            return $this->notFound($response, 'The requested user was not found.');
        }

        $action = $request->getQueryParams()['action'] ?? 'show';

        if ($action === 'edit') {
            return $this->twig->render($response, 'admin/users/edit.twig', [
                'pageTitle'  => 'Edit User',
                'user'       => $user,
                'csrf_token' => $this->getCsrfToken($request),
            ]);
        }

        return $this->twig->render($response, 'admin/users/show.twig', [
            'pageTitle'  => 'User Details',
            'user'       => $user,
            'csrf_token' => $this->getCsrfToken($request),
        ]);
    }

    /**
     * POST /admin/users - Creates a new user from form data.
     *
     * @param Request  $request  The incoming request with form data.
     * @param Response $response The outgoing response.
     * @return Response A redirect on success or a re-rendered form on error.
     */
    public function post(Request $request, Response $response): Response
    {
        $body      = $request->getParsedBody();
        $username  = $this->formString($body, 'username');
        $password  = $this->formRawString($body, 'password');
        $confirm   = $this->formRawString($body, 'password_confirm');
        $email     = $this->formString($body, 'email_address');
        $firstName = $this->formString($body, 'first_name');
        $lastName  = $this->formString($body, 'last_name');

        if ($firstName === '') {
            $firstName = null;
        }

        if ($lastName === '') {
            $lastName = null;
        }

        $old = [
            'username'      => $username,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'email_address' => $email,
        ];

        // Verify both password fields match before hashing.
        if ($password !== $confirm) {
            return $this->twig->render($response, 'admin/users/create.twig', [
                'pageTitle'  => 'Create User',
                'error'      => 'The password fields do not match.',
                'csrf_token' => $this->getCsrfToken($request),
                'old'        => $old,
            ]);
        }

        try {
            $this->userService->createUser($username, $password, $email, $firstName, $lastName);

            return $this->redirect($response, '/admin/users');
        } catch (InvalidArgumentException $exception) {
            return $this->twig->render($response, 'admin/users/create.twig', [
                'pageTitle'  => 'Create User',
                'error'      => $exception->getMessage(),
                'csrf_token' => $this->getCsrfToken($request),
                'old'        => $old,
            ]);
        }
    }

    /**
     * PUT /admin/users/{id} - Updates an existing user record.
     *
     * When the request body contains a new_password field, the method
     * performs a password reset instead of a profile update. This
     * allows the user detail page to submit both the profile form and
     * the password-reset form to the same RESTful endpoint.
     *
     * @param Request  $request  The incoming request with form data.
     * @param Response $response The outgoing response.
     * @return Response A redirect on success or a re-rendered form on error.
     */
    public function put(Request $request, Response $response): Response
    {
        $userId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($userId) === false) {
            return $this->notFound($response, 'The user identifier is not valid.');
        }

        $body = $request->getParsedBody();

        // Dispatch to the password reset handler when password fields
        // are present in the request body.
        $newPass = $this->formRawString($body, 'new_password');
        if ($newPass !== '') {
            return $this->handlePasswordReset($userId, $body, $request, $response);
        }

        return $this->handleProfileUpdate($userId, $body, $request, $response);
    }

    /**
     * DELETE /admin/users/{id} - Deletes a user record.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A redirect to the user list.
     */
    public function delete(Request $request, Response $response): Response
    {
        $userId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($userId) === false) {
            return $this->notFound($response, 'The user identifier is not valid.');
        }

        $this->userService->deleteUser($userId);

        return $this->redirect($response, '/admin/users');
    }

    /**
     * Handles a user profile update submitted through the PUT endpoint.
     *
     * @param string                    $userId   The target user identifier.
     * @param array<string, mixed>|null $body     The parsed request body.
     * @param Request                   $request  The incoming request.
     * @param Response                  $response The outgoing response.
     * @return Response A redirect on success or a re-rendered edit form.
     */
    private function handleProfileUpdate(
        string $userId,
        ?array $body,
        Request $request,
        Response $response
    ): Response {
        $username  = $this->formString($body, 'username');
        $email     = $this->formString($body, 'email_address');
        $firstName = $this->formString($body, 'first_name');
        $lastName  = $this->formString($body, 'last_name');

        if ($firstName === '') {
            $firstName = null;
        }

        if ($lastName === '') {
            $lastName = null;
        }

        try {
            $user = $this->userService->updateUser($userId, $username, $email, $firstName, $lastName);

            if ($user === null) {
                return $this->notFound($response, 'The requested user was not found.');
            }

            return $this->redirect($response, '/admin/users/' . $userId);
        } catch (InvalidArgumentException $exception) {
            $existingUser = $this->userService->getUserById($userId);

            return $this->twig->render($response, 'admin/users/edit.twig', [
                'pageTitle'  => 'Edit User',
                'error'      => $exception->getMessage(),
                'user'       => $existingUser,
                'csrf_token' => $this->getCsrfToken($request),
            ]);
        }
    }

    /**
     * Handles a password reset submitted through the PUT endpoint.
     *
     * @param string                    $userId   The target user identifier.
     * @param array<string, mixed>|null $body     The parsed request body.
     * @param Request                   $request  The incoming request.
     * @param Response                  $response The outgoing response.
     * @return Response A redirect on success or a re-rendered detail page.
     */
    private function handlePasswordReset(
        string $userId,
        ?array $body,
        Request $request,
        Response $response
    ): Response {
        $newPass = $this->formRawString($body, 'new_password');
        $confirm = $this->formRawString($body, 'new_password_confirm');

        $user = $this->userService->getUserById($userId);
        if ($user === null) {
            return $this->notFound($response, 'The requested user was not found.');
        }

        if ($newPass !== $confirm) {
            return $this->twig->render($response, 'admin/users/show.twig', [
                'pageTitle'      => 'User Details',
                'user'           => $user,
                'csrf_token'     => $this->getCsrfToken($request),
                'password_error' => 'The password fields do not match.',
            ]);
        }

        try {
            $this->userService->resetPassword($userId, $newPass);

            return $this->redirect($response, '/admin/users/' . $userId);
        } catch (InvalidArgumentException $exception) {
            return $this->twig->render($response, 'admin/users/show.twig', [
                'pageTitle'      => 'User Details',
                'user'           => $user,
                'csrf_token'     => $this->getCsrfToken($request),
                'password_error' => $exception->getMessage(),
            ]);
        }
    }
}
