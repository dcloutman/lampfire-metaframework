<?php

declare(strict_types=1);

/**
 * Admin web controller for access-control management views.
 *
 * Renders the admin page that manages permissions, permission sets,
 * user groups, and user-group to permission-set assignments.
 */

namespace App\Controllers\Admin;

use App\Middleware\AdminAuthorizationMiddleware;
use App\Middleware\AuthMiddleware;
use App\Services\PermissionService;
use App\Services\UserGroupService;
use Lampfire\Controllers\AbstractAdminController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AccessControlAdminController extends AbstractAdminController
{
    protected string $routePrefix = '/admin/access-control';
    protected string $idPattern = '/{id:[a-z\-]+}';
    protected array $routeMiddleware = [AdminAuthorizationMiddleware::class, AuthMiddleware::class];

    private const SECTION_PERMISSIONS = 'permissions';
    private const SECTION_USER_GROUPS = 'user-groups';
    private const SECTION_PERMISSION_SETS = 'permission-sets';
    private const SECTION_SET_PERMISSIONS = 'set-permissions';
    private const SECTION_SET_MEMBERS = 'set-members';
    private const SECTION_SET_GROUP_ASSIGNMENTS = 'set-group-assignments';
    private const SECTION_USER_GROUP_MEMBERSHIPS = 'user-group-memberships';

    /**
     * @var PermissionService Service for permission CRUD operations.
     */
    private PermissionService $permissionService;

    /**
     * @var UserGroupService Service for user-group operations.
     */
    private UserGroupService $userGroupService;

    /**
     * Creates the access-control admin controller.
     *
     * @param Twig $twig The Twig view renderer.
     */
    public function __construct(
        Twig $twig,
        PermissionService $permissionService,
        UserGroupService $userGroupService
    )
    {
        parent::__construct($twig);
        $this->permissionService = $permissionService;
        $this->userGroupService = $userGroupService;
    }

    /**
     * GET /admin/access-control - Renders the default access-control section.
     *
     * @param Request $request The incoming request.
     * @param Response $response The outgoing response.
     * @return Response The rendered page.
     */
    public function get(Request $request, Response $response): Response
    {
        $section = (string) ($request->getQueryParams()['section'] ?? self::SECTION_PERMISSIONS);

        return $this->renderSection($request, $response, $this->normalizeSection($section));
    }

    /**
     * GET /admin/access-control/{id} - Renders a specific access-control section.
     *
     * @param Request $request The incoming request.
     * @param Response $response The outgoing response.
     * @return Response The rendered page.
     */
    public function getById(Request $request, Response $response): Response
    {
        $section = $this->routeArgument($request, 'id');

        return $this->renderSection($request, $response, $this->normalizeSection($section));
    }

    /**
     * Renders the access-control page with one active section visible.
     *
     * @param Request $request The incoming request.
     * @param Response $response The outgoing response.
     * @param string $activeSection The section identifier to show.
     * @return Response The rendered page.
     */
    private function renderSection(Request $request, Response $response, string $activeSection): Response
    {
        if ($activeSection === self::SECTION_SET_GROUP_ASSIGNMENTS) {
            return $this->twig->render($response, 'admin/group_to_permission_set_assignment.twig', array_merge([
                'pageTitle' => 'Group to Permission Set Assignment',
                'csrf_token' => $this->getCsrfToken($request),
            ], $this->getFlashViewData($request)));
        }

        if ($activeSection === self::SECTION_USER_GROUP_MEMBERSHIPS) {
            return $this->twig->render($response, 'admin/user_group_memberships.twig', array_merge([
                'pageTitle' => 'User Group Memberships',
                'csrf_token' => $this->getCsrfToken($request),
            ], $this->getFlashViewData($request)));
        }

        $permissions = [];
        if ($activeSection === self::SECTION_PERMISSIONS) {
            $permissions = $this->permissionService->getAllPermissions();
        }

        return $this->twig->render($response, 'admin/access_control.twig', array_merge([
            'pageTitle' => 'Access Control',
            'active_section' => $activeSection,
            'permissions' => $permissions,
            'admin_group_name' => $this->userGroupService->getAdministrativeGroupName(),
            'admin_permission_token_prefix' => $this->permissionService->getAdministrativePermissionTokenPrefix(),
            'csrf_token' => $this->getCsrfToken($request),
        ], $this->getFlashViewData($request)));
    }

    /**
     * Converts an arbitrary section value into a known section identifier.
     *
     * @param string $section The raw section query value.
     * @return string A valid access-control section key.
     */
    private function normalizeSection(string $section): string
    {
        $allowedSections = [
            self::SECTION_PERMISSIONS,
            self::SECTION_USER_GROUPS,
            self::SECTION_PERMISSION_SETS,
            self::SECTION_SET_PERMISSIONS,
            self::SECTION_SET_MEMBERS,
            self::SECTION_SET_GROUP_ASSIGNMENTS,
            self::SECTION_USER_GROUP_MEMBERSHIPS,
        ];

        if (in_array($section, $allowedSections, true)) {
            return $section;
        }

        return self::SECTION_PERMISSIONS;
    }
}
