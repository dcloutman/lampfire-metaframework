<?php
/**
 * AdminGuard enforces protection rules for administrative resources.
 *
 * This service prevents modification of protected admin resources through
 * the API. The superadmin (an ordinary user whose username matches the
 * SUPERADMIN_USERNAME environment variable) can bypass these restrictions
 * to modify:
 *   - The admin user group membership
 *   - The admin permission set permissions
 *   - The association between the admin permission set and admin user group
 *
 * @package App\Auth
 */

declare(strict_types=1);

namespace App\Auth;

/**
 * Guards administrative resources from unauthorized modification.
 *
 * The admin user group and admin permission set are special entities that
 * cannot be modified through normal API operations. This guard provides
 * methods to check whether an operation targets these protected resources.
 */
class AdminGuard
{
    /**
     * @var string The UUID of the protected admin user group.
     */
    private string $adminUserGroupId;

    /**
     * @var string The UUID of the protected admin permission set.
     */
    private string $adminPermissionSetId;

    /**
     * Creates the admin guard.
     *
     * @param string $adminUserGroupId     The UUID of the admin user group.
     * @param string $adminPermissionSetId The UUID of the admin permission set.
     */
    public function __construct(string $adminUserGroupId, string $adminPermissionSetId)
    {
        $this->adminUserGroupId = $adminUserGroupId;
        $this->adminPermissionSetId = $adminPermissionSetId;
    }

    /**
     * Checks if the given user group ID is the protected admin group.
     *
     * @param string $userGroupId The user group ID to check.
     * @return bool True if this is the admin user group.
     */
    public function isAdminUserGroup(string $userGroupId): bool
    {
        return $userGroupId === $this->adminUserGroupId;
    }

    /**
     * Checks if the given permission set ID is the protected admin set.
     *
     * @param string $permissionSetId The permission set ID to check.
     * @return bool True if this is the admin permission set.
     */
    public function isAdminPermissionSet(string $permissionSetId): bool
    {
        return $permissionSetId === $this->adminPermissionSetId;
    }

    /**
     * Checks if the operation involves the admin user group.
     *
     * Used to protect user group membership operations. Adding or removing
     * users from the admin user group requires superadmin privileges.
     *
     * @param array<string, mixed> $data Request data containing user_group_id.
     * @return bool True if the operation targets the admin user group.
     */
    public function operationTargetsAdminUserGroup(array $data): bool
    {
        $userGroupId = $data['user_group_id'] ?? '';
        return $this->isAdminUserGroup($userGroupId);
    }

    /**
     * Checks if the operation involves the admin permission set.
     *
     * Used to protect permission set operations. Modifying the admin
     * permission set requires superadmin privileges.
     *
     * @param array<string, mixed> $data Request data containing permission_set_id.
     * @return bool True if the operation targets the admin permission set.
     */
    public function operationTargetsAdminPermissionSet(array $data): bool
    {
        $permissionSetId = $data['permission_set_id'] ?? '';
        return $this->isAdminPermissionSet($permissionSetId);
    }

    /**
     * Checks if a permission set group member association is protected.
     *
     * The association between the admin user group and admin permission set
     * is immutable through the API. This method checks if an operation would
     * affect this protected association.
     *
     * @param string $userGroupId     The user group ID.
     * @param string $permissionSetId The permission set ID.
     * @return bool True if this association is protected.
     */
    public function isProtectedAssociation(string $userGroupId, string $permissionSetId): bool
    {
        return $this->isAdminUserGroup($userGroupId) && $this->isAdminPermissionSet($permissionSetId);
    }

    /**
     * Returns the admin user group ID.
     *
     * @return string The UUID of the admin user group.
     */
    public function getAdminUserGroupId(): string
    {
        return $this->adminUserGroupId;
    }

    /**
     * Returns the admin permission set ID.
     *
     * @return string The UUID of the admin permission set.
     */
    public function getAdminPermissionSetId(): string
    {
        return $this->adminPermissionSetId;
    }
}
