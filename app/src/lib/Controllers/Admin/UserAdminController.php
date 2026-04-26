<?php

declare(strict_types=1);

/**
 * Admin web controller for user management.
 *
 * Handles listing, creating, editing, deleting, and password
 * resets for user records. All data operations are delegated to the
 * UserService. Templates are rendered server-side with Twig.
 *
 * This controller follows RESTful conventions. The routePrefix property
 * provides the full URI base. HTML forms that need PUT or DELETE use a
 * hidden _METHOD field processed by MethodOverrideMiddleware.
 */

namespace App\Controllers\Admin;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use App\Middleware\AdminAuthorizationMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\UserGroupService;
use Lampfire\Services\UserService;
use InvalidArgumentException;
use Lampfire\Controllers\AbstractAdminController;
use Lampfire\Routing\Route;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Throwable;

class UserAdminController extends AbstractAdminController
{
    protected string $routePrefix = '/admin/users';
    protected array $routeMiddleware = [AdminAuthorizationMiddleware::class, AuthMiddleware::class];

    private const MESSAGE_PASSWORD_FIELDS_MUST_MATCH = 'Password fields must match.';
    private const MESSAGE_CANNOT_DISABLE_OWN_ACCOUNT = 'You cannot disable your own account.';
    private const MESSAGE_USER_CREATED_SUCCESS = 'User was created successfully.';
    private const MESSAGE_USER_PROFILE_UPDATED_SUCCESS = 'User profile was updated successfully.';
    private const MESSAGE_PASSWORD_RESET_SUCCESS = 'Password was reset successfully.';

    private const MESSAGE_GROUP_SELECT_REQUIRED = 'Please select a user group to assign.';
    private const MESSAGE_GROUP_MEMBERSHIP_CREATED_SUCCESS = 'User group membership was created successfully.';
    private const MESSAGE_GROUP_MEMBERSHIP_UPDATED_SUCCESS = 'User group membership was updated successfully.';
    private const MESSAGE_GROUP_MEMBERSHIP_DISABLED_SUCCESS = 'User group membership was disabled successfully.';
    private const MESSAGE_GROUP_MEMBERSHIP_NOT_FOUND = 'The membership does not exist.';
    private const MESSAGE_GROUP_MEMBERSHIP_EXPIRY_REQUIRED = 'The membership expiry is required.';
    private const MESSAGE_GROUP_MEMBERSHIP_MISSING_GRANTED = 'The membership record is missing its access grant date.';
    private const MESSAGE_GROUP_MEMBERSHIP_CREATE_FAILED =
        'Membership could not be created. The user may already be assigned to this group.';

    private const MESSAGE_ADMIN_GROUP_MODIFY_FORBIDDEN =
        'Only the superadmin can modify memberships for the administrative user group.';
    private const MESSAGE_ADMIN_GROUP_DISABLE_FORBIDDEN =
        'Only the superadmin can disable memberships in the administrative user group.';

    private const MESSAGE_GROUP_INVALID =
        'The selected user group is invalid. Please refresh the page and try again.';
    private const MESSAGE_GROUP_MISSING =
        'The selected user group no longer exists. Please refresh the page and try again.';
    private const MESSAGE_GROUP_DATE_FORMAT_INVALID =
        'Membership dates must use UTC format YYYY-MM-DD HH:MM:SS.';
    private const MESSAGE_GROUP_EXPIRY_BEFORE_GRANTED =
        'Membership expiry must be later than the original access grant time.';
    private const MESSAGE_GROUP_MAX_DURATION_EXCEEDED =
        'User group access can be granted for a maximum of two years.';
    private const MESSAGE_GROUP_UPDATE_FAILED =
        'User group membership could not be updated. Please try again.';

    /**
     * @var UserService The user business logic service.
     */
    private UserService $userService;

    /**
     * @var UserGroupService The user-group business logic service.
     */
    private UserGroupService $userGroupService;

    /**
     * Creates the admin user controller.
     *
     * @param UserService      $userService      The user business logic service.
     * @param UserGroupService $userGroupService The user-group business logic service.
     * @param Twig             $twig             The Twig view renderer.
     */
    public function __construct(UserService $userService, UserGroupService $userGroupService, Twig $twig)
    {
        parent::__construct($twig);
        $this->userService = $userService;
        $this->userGroupService = $userGroupService;
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
        $flashData = $this->getFlashViewData($request);

        if ($action === 'create') {
            return $this->twig->render($response, 'admin/users/create.twig', array_merge([
                'pageTitle'  => 'Create User',
                'csrf_token' => $this->getCsrfToken($request),
            ], $flashData));
        }

        $users = $this->userService->getAllUsers();

        return $this->twig->render($response, 'admin/users/index.twig', array_merge([
            'pageTitle'  => 'Users',
            'users'      => $users,
            'csrf_token' => $this->getCsrfToken($request),
        ], $flashData));
    }

    /**
     * GET /admin/users/{id} - Renders the user edit page.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response The rendered user edit page.
     */
    public function getById(Request $request, Response $response): Response
    {
        $userId = $this->routeArgument($request, 'id');
        $authUsername = (string) $request->getAttribute('auth_username', '');
        $flashData = $this->getFlashViewData($request);

        if ($this->isValidUuid($userId) === false) {
            return $this->notFound($response, 'The user identifier is not valid.');
        }

        $user = $this->userService->getUserById($userId);

        if ($user === null) {
            return $this->notFound($response, 'The requested user was not found.');
        }

        $groupData = $this->buildGroupDataForUser($userId, $authUsername);

        return $this->twig->render($response, 'admin/users/edit.twig', array_merge([
            'pageTitle'    => 'Edit User',
            'user'         => $user,
            'auth_user_id' => (string) $request->getAttribute('auth_user_id', ''),
            'csrf_token'   => $this->getCsrfToken($request),
        ], $groupData, $flashData));
    }

    /**
     * POST /admin/users/{id}/groups - Adds the user to the selected group.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A redirect or a re-rendered detail page on error.
     */
    #[Route('POST', '/{id}/groups')]
    public function addUserToGroup(Request $request, Response $response): Response
    {
        $userId = $this->routeArgument($request, 'id');

        if ($this->isValidUuid($userId) === false) {
            return $this->notFound($response, 'The user identifier is not valid.');
        }

        $user = $this->userService->getUserById($userId);
        if ($user === null) {
            return $this->notFound($response, 'The requested user was not found.');
        }

        $body = $request->getParsedBody();
        $userGroupId = $this->formString($body, 'user_group_id');

        if ($userGroupId === '') {
            return $this->renderUserEditWithGroupError(
                $request,
                $response,
                $user,
                self::MESSAGE_GROUP_SELECT_REQUIRED
            );
        }

        try {
            $accessGrantedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            $this->userGroupService->addMember(
                $userId,
                $userGroupId,
                $accessGrantedAt->format('Y-m-d H:i:s'),
                $accessGrantedAt->add(new DateInterval('P2Y'))->format('Y-m-d H:i:s'),
                true
            );

            return $this->redirectWithSuccess(
                $response,
                '/admin/users/' . $userId,
                self::MESSAGE_GROUP_MEMBERSHIP_CREATED_SUCCESS
            );
        } catch (InvalidArgumentException $exception) {
            return $this->renderUserEditWithGroupError(
                $request,
                $response,
                $user,
                $this->mapGroupMembershipErrorMessage($exception->getMessage())
            );
        } catch (Throwable $exception) {
            return $this->renderUserEditWithGroupError(
                $request,
                $response,
                $user,
                self::MESSAGE_GROUP_MEMBERSHIP_CREATE_FAILED
            );
        }
    }

    /**
     * POST /admin/users/{id}/groups/{groupId}/update - Updates membership expiry or status.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A redirect or a re-rendered detail page on error.
     */
    #[Route('POST', '/{id}/groups/{groupId}/update')]
    public function updateUserGroupMembership(Request $request, Response $response): Response
    {
        $userId = $this->routeArgument($request, 'id');
        $groupId = $this->routeArgument($request, 'groupId');
        $authUsername = (string) $request->getAttribute('auth_username', '');

        if ($this->isValidUuid($userId) === false || $this->isValidUuid($groupId) === false) {
            return $this->notFound($response, 'The requested identifiers are not valid.');
        }

        $user = $this->userService->getUserById($userId);
        if ($user === null) {
            return $this->notFound($response, 'The requested user was not found.');
        }

        $isSuperadmin = $this->userService->isSuperadminUsername($authUsername);
        if ($isSuperadmin === false && $this->userGroupService->isAdministrativeGroupId($groupId)) {
            return $this->renderUserEditWithGroupError(
                $request,
                $response,
                $user,
                self::MESSAGE_ADMIN_GROUP_MODIFY_FORBIDDEN
            );
        }

        $existingMembership = $this->userGroupService->getMembership($userId, $groupId);
        if ($existingMembership === null) {
            return $this->renderUserEditWithGroupError(
                $request,
                $response,
                $user,
                self::MESSAGE_GROUP_MEMBERSHIP_NOT_FOUND
            );
        }

        $body = $request->getParsedBody();
        $accessExpiry = $this->formString($body, 'access_expiry');
        $hasAccess = $this->formString($body, 'has_access') === '1';

        if ($accessExpiry === '') {
            return $this->renderUserEditWithGroupError(
                $request,
                $response,
                $user,
                self::MESSAGE_GROUP_MEMBERSHIP_EXPIRY_REQUIRED
            );
        }

        $accessGranted = is_string($existingMembership['access_granted'] ?? null)
            ? $existingMembership['access_granted']
            : '';

        if ($accessGranted === '') {
            return $this->renderUserEditWithGroupError(
                $request,
                $response,
                $user,
                self::MESSAGE_GROUP_MEMBERSHIP_MISSING_GRANTED
            );
        }

        try {
            $updatedMembership = $this->userGroupService->updateMembership(
                $userId,
                $groupId,
                $accessGranted,
                $accessExpiry,
                $hasAccess
            );

            if ($updatedMembership === null) {
                return $this->renderUserEditWithGroupError(
                    $request,
                    $response,
                    $user,
                    self::MESSAGE_GROUP_MEMBERSHIP_NOT_FOUND
                );
            }

            return $this->redirectWithSuccess(
                $response,
                '/admin/users/' . $userId,
                self::MESSAGE_GROUP_MEMBERSHIP_UPDATED_SUCCESS
            );
        } catch (InvalidArgumentException $exception) {
            return $this->renderUserEditWithGroupError(
                $request,
                $response,
                $user,
                $this->mapGroupMembershipErrorMessage($exception->getMessage())
            );
        }
    }

    /**
     * POST /admin/users/{id}/groups/{groupId}/remove - Disables access for a membership.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A redirect or a re-rendered detail page on error.
     */
    #[Route('POST', '/{id}/groups/{groupId}/remove')]
    public function removeUserFromGroup(Request $request, Response $response): Response
    {
        $userId = $this->routeArgument($request, 'id');
        $groupId = $this->routeArgument($request, 'groupId');
        $authUsername = (string) $request->getAttribute('auth_username', '');

        if ($this->isValidUuid($userId) === false || $this->isValidUuid($groupId) === false) {
            return $this->notFound($response, 'The requested identifiers are not valid.');
        }

        $user = $this->userService->getUserById($userId);
        if ($user === null) {
            return $this->notFound($response, 'The requested user was not found.');
        }

        $isSuperadmin = $this->userService->isSuperadminUsername($authUsername);
        if ($isSuperadmin === false && $this->userGroupService->isAdministrativeGroupId($groupId)) {
            return $this->renderUserEditWithGroupError(
                $request,
                $response,
                $user,
                self::MESSAGE_ADMIN_GROUP_DISABLE_FORBIDDEN
            );
        }

        try {
            $disabled = $this->userGroupService->removeMember($userId, $groupId);
            if ($disabled === false) {
                return $this->renderUserEditWithGroupError(
                    $request,
                    $response,
                    $user,
                    self::MESSAGE_GROUP_MEMBERSHIP_NOT_FOUND
                );
            }

            return $this->redirectWithSuccess(
                $response,
                '/admin/users/' . $userId,
                self::MESSAGE_GROUP_MEMBERSHIP_DISABLED_SUCCESS
            );
        } catch (InvalidArgumentException $exception) {
            return $this->renderUserEditWithGroupError(
                $request,
                $response,
                $user,
                $this->mapGroupMembershipErrorMessage($exception->getMessage())
            );
        }
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
                'error'      => self::MESSAGE_PASSWORD_FIELDS_MUST_MATCH,
                'csrf_token' => $this->getCsrfToken($request),
                'old'        => $old,
            ]);
        }

        try {
            $this->userService->createUser($username, $password, $email, $firstName, $lastName);

            return $this->redirectWithSuccess(
                $response,
                '/admin/users',
                self::MESSAGE_USER_CREATED_SUCCESS
            );
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
     * DELETE /admin/users/{id} - Deleting users is not supported.
     *
     * @param Request  $request  The incoming request.
     * @param Response $response The outgoing response.
     * @return Response A redirect to the user list.
     */
    public function delete(Request $request, Response $response): Response
    {
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
        $authUserId = (string) $request->getAttribute('auth_user_id', '');
        $email      = $this->formString($body, 'email_address');
        $firstName  = $this->formString($body, 'first_name');
        $lastName   = $this->formString($body, 'last_name');
        $enabled    = $this->formString($body, 'enabled') === '1';

        if ($userId === $authUserId && $enabled === false) {
            $existingUser = $this->userService->getUserById($userId);
            $groupData = $this->buildGroupDataForUser(
                $userId,
                (string) $request->getAttribute('auth_username', '')
            );

            return $this->twig->render($response, 'admin/users/edit.twig', [
                'pageTitle'    => 'Edit User',
                'error'        => self::MESSAGE_CANNOT_DISABLE_OWN_ACCOUNT,
                'user'         => $existingUser,
                'auth_user_id' => $authUserId,
                'csrf_token'   => $this->getCsrfToken($request),
            ] + $groupData);
        }

        if ($firstName === '') {
            $firstName = null;
        }

        if ($lastName === '') {
            $lastName = null;
        }

        try {
            $user = $this->userService->updateUser($userId, $email, $enabled, $firstName, $lastName);

            if ($user === null) {
                return $this->notFound($response, 'The requested user was not found.');
            }

            return $this->redirectWithSuccess(
                $response,
                '/admin/users/' . $userId,
                self::MESSAGE_USER_PROFILE_UPDATED_SUCCESS
            );
        } catch (InvalidArgumentException $exception) {
            $existingUser = $this->userService->getUserById($userId);
            $groupData = $this->buildGroupDataForUser(
                $userId,
                (string) $request->getAttribute('auth_username', '')
            );

            return $this->twig->render($response, 'admin/users/edit.twig', [
                'pageTitle'    => 'Edit User',
                'error'        => $exception->getMessage(),
                'user'         => $existingUser,
                'auth_user_id' => (string) $request->getAttribute('auth_user_id', ''),
                'csrf_token'   => $this->getCsrfToken($request),
            ] + $groupData);
        }
    }

    /**
     * Handles a password reset submitted through the PUT endpoint.
     *
     * @param string                    $userId   The target user identifier.
     * @param array<string, mixed>|null $body     The parsed request body.
     * @param Request                   $request  The incoming request.
     * @param Response                  $response The outgoing response.
        * @return Response A redirect on success or a re-rendered edit page.
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
            $groupData = $this->buildGroupDataForUser(
                $userId,
                (string) $request->getAttribute('auth_username', '')
            );

            return $this->twig->render($response, 'admin/users/edit.twig', array_merge([
                'pageTitle'      => 'Edit User',
                'user'           => $user,
                'auth_user_id'   => (string) $request->getAttribute('auth_user_id', ''),
                'csrf_token'     => $this->getCsrfToken($request),
                'password_error' => self::MESSAGE_PASSWORD_FIELDS_MUST_MATCH,
            ], $groupData));
        }

        try {
            $this->userService->resetPassword($userId, $newPass);

            return $this->redirectWithSuccess(
                $response,
                '/admin/users/' . $userId,
                self::MESSAGE_PASSWORD_RESET_SUCCESS
            );
        } catch (InvalidArgumentException $exception) {
            $groupData = $this->buildGroupDataForUser(
                $userId,
                (string) $request->getAttribute('auth_username', '')
            );

            return $this->twig->render($response, 'admin/users/edit.twig', array_merge([
                'pageTitle'      => 'Edit User',
                'user'           => $user,
                'auth_user_id'   => (string) $request->getAttribute('auth_user_id', ''),
                'csrf_token'     => $this->getCsrfToken($request),
                'password_error' => $exception->getMessage(),
            ], $groupData));
        }
    }

    /**
     * Builds membership and available-group lists for the user detail page.
     *
     * @param string $userId       The target user identifier.
     * @param string $authUsername The authenticated username.
     * @return array<string, mixed> Data for Twig rendering.
     */
    private function buildGroupDataForUser(string $userId, string $authUsername): array
    {
        $memberships = $this->userGroupService->getMembershipsByUserId($userId);
        $groups = $this->userGroupService->getAllGroups();
        $isSuperadmin = $this->userService->isSuperadminUsername($authUsername);
        $adminGroupName = $this->userGroupService->getAdministrativeGroupName();

        $groupsById = [];
        foreach ($groups as $group) {
            $groupId = (string) ($group['user_group_id'] ?? '');
            if ($groupId !== '') {
                $groupsById[$groupId] = $group;
            }
        }

        $membershipGroupIds = [];
        $membershipsWithGroup = [];

        foreach ($memberships as $membership) {
            $groupId = (string) ($membership['user_group_id'] ?? '');
            if ($groupId === '') {
                continue;
            }

            $membershipGroupIds[$groupId] = true;
            $membership['group_name'] = $groupsById[$groupId]['group_name'] ?? $groupId;
            $membership['group_description'] = $groupsById[$groupId]['description'] ?? null;
            $isAdministrativeGroup = is_string($membership['group_name'])
                && $adminGroupName !== ''
                && $membership['group_name'] === $adminGroupName;
            $membership['is_admin_group'] = $isAdministrativeGroup;
            $membership['can_remove'] = $isSuperadmin || $isAdministrativeGroup === false;
            $membershipsWithGroup[] = $membership;
        }

        $availableGroups = [];
        foreach ($groups as $group) {
            $groupId = (string) ($group['user_group_id'] ?? '');
            if ($groupId === '') {
                continue;
            }

            if (array_key_exists($groupId, $membershipGroupIds)) {
                continue;
            }

            $availableGroups[] = $group;
        }

        return [
            'group_memberships' => $membershipsWithGroup,
            'available_groups'  => $availableGroups,
            'is_superadmin'     => $isSuperadmin,
        ];
    }

    /**
        * Renders the user edit page with a group-management error message.
     *
     * @param Request               $request   The incoming request.
     * @param Response              $response  The outgoing response.
     * @param array<string, mixed>  $user      The user record.
     * @param string                $errorText The message to display.
     * @return Response The rendered edit page.
     */
    private function renderUserEditWithGroupError(
        Request $request,
        Response $response,
        array $user,
        string $errorText
    ): Response {
        $userId = (string) ($user['user_id'] ?? '');
        $authUsername = (string) $request->getAttribute('auth_username', '');
        $groupData = $userId !== '' ? $this->buildGroupDataForUser($userId, $authUsername) : [
            'group_memberships' => [],
            'available_groups'  => [],
            'is_superadmin'     => false,
        ];

        return $this->twig->render($response, 'admin/users/edit.twig', array_merge([
            'pageTitle'    => 'Edit User',
            'user'         => $user,
            'auth_user_id' => (string) $request->getAttribute('auth_user_id', ''),
            'csrf_token'   => $this->getCsrfToken($request),
            'group_error'  => $errorText,
        ], $groupData));
    }

    /**
     * Maps internal validation errors to end-user-friendly messages.
     *
     * @param string $message The internal exception message.
     * @return string A user-facing message.
     */
    private function mapGroupMembershipErrorMessage(string $message): string
    {
        if (str_contains($message, 'valid UUID')) {
            return self::MESSAGE_GROUP_INVALID;
        }

        if (str_contains($message, 'does not exist')) {
            return self::MESSAGE_GROUP_MISSING;
        }

        if (str_contains($message, 'UTC format')) {
            return self::MESSAGE_GROUP_DATE_FORMAT_INVALID;
        }

        if (str_contains($message, 'later than access_granted')) {
            return self::MESSAGE_GROUP_EXPIRY_BEFORE_GRANTED;
        }

        if (str_contains($message, 'two years')) {
            return self::MESSAGE_GROUP_MAX_DURATION_EXCEEDED;
        }

        return self::MESSAGE_GROUP_UPDATE_FAILED;
    }
}
