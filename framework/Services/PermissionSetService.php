<?php

declare(strict_types=1);

/**
 * Business logic service for permission set management.
 *
 * Coordinates operations on PermissionSets and the three related
 * junction tables: PermissionSetPermissions, PermissionSetMembers,
 * and PermissionSetUserGroups. Validates inputs, enforces
 * referential integrity through existence checks, and generates
 * UUIDs for new master records.
 */

namespace Lampfire\Services;

use Lampfire\Gateways\PermissionGateway;
use Lampfire\Gateways\PermissionSetGateway;
use Lampfire\Gateways\PermissionSetUserGroupGateway;
use Lampfire\Gateways\PermissionSetMemberGateway;
use Lampfire\Gateways\PermissionSetPermissionGateway;
use Lampfire\Gateways\UserGateway;
use Lampfire\Gateways\UserGroupGateway;
use InvalidArgumentException;

class PermissionSetService extends AbstractService
{
    private PermissionSetGateway $setGateway;
    private PermissionSetPermissionGateway $setPermissionGateway;
    private PermissionSetMemberGateway $setMemberGateway;
    private PermissionSetUserGroupGateway $setGroupMemberGateway;
    private PermissionGateway $permissionGateway;
    private UserGateway $userGateway;
    private UserGroupGateway $groupGateway;

    /**
     * Creates the permission set service.
     *
     * @param PermissionSetGateway            $setGateway            Gateway for PermissionSets.
     * @param PermissionSetPermissionGateway  $setPermissionGateway  Gateway for PermissionSetPermissions.
     * @param PermissionSetMemberGateway      $setMemberGateway      Gateway for PermissionSetMembers.
     * @param PermissionSetUserGroupGateway $setGroupMemberGateway Gateway for PermissionSetUserGroups.
     * @param PermissionGateway               $permissionGateway     Gateway for Permissions.
     * @param UserGateway                     $userGateway           Gateway for Users.
     * @param UserGroupGateway                $groupGateway          Gateway for UserGroups.
     */
    public function __construct(
        PermissionSetGateway $setGateway,
        PermissionSetPermissionGateway $setPermissionGateway,
        PermissionSetMemberGateway $setMemberGateway,
        PermissionSetUserGroupGateway $setGroupMemberGateway,
        PermissionGateway $permissionGateway,
        UserGateway $userGateway,
        UserGroupGateway $groupGateway
    ) {
        $this->setGateway = $setGateway;
        $this->setPermissionGateway = $setPermissionGateway;
        $this->setMemberGateway = $setMemberGateway;
        $this->setGroupMemberGateway = $setGroupMemberGateway;
        $this->permissionGateway = $permissionGateway;
        $this->userGateway = $userGateway;
        $this->groupGateway = $groupGateway;
    }

    // ----------------------------------------------------------------
    // PermissionSets CRUD
    // ----------------------------------------------------------------

    /**
     * Returns all permission sets.
     *
     * @return array<int, array<string, mixed>> The list of permission sets.
     */
    public function getAllSets(): array
    {
        return $this->setGateway->findAll();
    }

    /**
     * Returns a single permission set by identifier.
     *
     * @param string $permissionSetId The UUID of the set.
     * @return array<string, mixed>|null The set or null when not found.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function getSetById(string $permissionSetId): ?array
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        return $this->setGateway->findById($permissionSetId);
    }

    /**
     * Creates a new permission set.
     *
     * @param string      $permissionSetToken The programmatic token.
     * @param string      $title              The human-readable title.
     * @param string|null $notes              Optional notes.
     * @return array<string, mixed> The newly created set record.
     * @throws InvalidArgumentException When validation or uniqueness checks fail.
     */
    public function createSet(string $permissionSetToken, string $title, ?string $notes = null): array
    {
        $this->requireMinLength($permissionSetToken, 2, 'permission_set_token');
        $this->requireMinLength($title, 2, 'title');

        if ($this->setGateway->tokenExists($permissionSetToken)) {
            throw new InvalidArgumentException('The permission set token is already in use.');
        }

        $permissionSetId = $this->generateUuid();
        $this->setGateway->insert($permissionSetId, $permissionSetToken, $title, $notes);

        return $this->setGateway->findById($permissionSetId);
    }

    /**
     * Updates an existing permission set.
     *
     * @param string      $permissionSetId    The UUID of the set.
     * @param string      $permissionSetToken The new token.
     * @param string      $title              The new title.
     * @param string|null $notes              The new notes.
     * @return array<string, mixed>|null The updated record or null when not found.
     * @throws InvalidArgumentException When validation or uniqueness checks fail.
     */
    public function updateSet(
        string $permissionSetId,
        string $permissionSetToken,
        string $title,
        ?string $notes = null
    ): ?array {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');
        $this->requireMinLength($permissionSetToken, 2, 'permission_set_token');
        $this->requireMinLength($title, 2, 'title');

        $existing = $this->setGateway->findById($permissionSetId);
        if ($existing === null) {
            return null;
        }

        if ($this->setGateway->tokenExists($permissionSetToken, $permissionSetId)) {
            throw new InvalidArgumentException('The permission set token is already in use.');
        }

        $this->setGateway->update($permissionSetId, $permissionSetToken, $title, $notes);

        return $this->setGateway->findById($permissionSetId);
    }

    /**
     * Deletes a permission set.
     *
     * @param string $permissionSetId The UUID of the set.
     * @return bool True when the set was deleted.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function deleteSet(string $permissionSetId): bool
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        $existing = $this->setGateway->findById($permissionSetId);
        if ($existing === null) {
            return false;
        }

        return $this->setGateway->delete($permissionSetId);
    }

    // ----------------------------------------------------------------
    // PermissionSetPermissions (permission-to-set associations)
    // ----------------------------------------------------------------

    /**
     * Returns all permissions assigned to a set.
     *
     * @param string $permissionSetId The UUID of the set.
     * @return array<int, array<string, mixed>> The list of associations.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function getPermissionsBySetId(string $permissionSetId): array
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        return $this->setPermissionGateway->findBySetId($permissionSetId);
    }

    /**
     * Returns a single permission-to-set association.
     *
     * @param string $permissionSetId The UUID of the set.
     * @param string $permissionId    The UUID of the permission.
     * @return array<string, mixed>|null The association or null when not found.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function getSetPermission(string $permissionSetId, string $permissionId): ?array
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');
        $this->requireValidUuid($permissionId, 'permission_id');

        return $this->setPermissionGateway->findByKey($permissionSetId, $permissionId);
    }

    /**
     * Assigns a permission to a set.
     *
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $permissionId    The UUID of the permission.
     * @param string|null $notes           Optional notes.
     * @return array<string, mixed> The newly created association.
     * @throws InvalidArgumentException When validation or existence checks fail.
     */
    public function addPermissionToSet(
        string $permissionSetId,
        string $permissionId,
        ?string $notes = null
    ): array {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');
        $this->requireValidUuid($permissionId, 'permission_id');

        if ($this->setGateway->findById($permissionSetId) === null) {
            throw new InvalidArgumentException('The specified permission set does not exist.');
        }

        if ($this->permissionGateway->findById($permissionId) === null) {
            throw new InvalidArgumentException('The specified permission does not exist.');
        }

        $this->setPermissionGateway->insert($permissionSetId, $permissionId, $notes);

        return $this->setPermissionGateway->findByKey($permissionSetId, $permissionId);
    }

    /**
     * Updates the notes on a permission-to-set association.
     *
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $permissionId    The UUID of the permission.
     * @param string|null $notes           The new notes.
     * @return array<string, mixed>|null The updated record or null when not found.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function updateSetPermission(
        string $permissionSetId,
        string $permissionId,
        ?string $notes = null
    ): ?array {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');
        $this->requireValidUuid($permissionId, 'permission_id');

        $existing = $this->setPermissionGateway->findByKey($permissionSetId, $permissionId);
        if ($existing === null) {
            return null;
        }

        $this->setPermissionGateway->update($permissionSetId, $permissionId, $notes);

        return $this->setPermissionGateway->findByKey($permissionSetId, $permissionId);
    }

    /**
     * Removes a permission from a set.
     *
     * @param string $permissionSetId The UUID of the set.
     * @param string $permissionId    The UUID of the permission.
     * @return bool True when the association was deleted.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function removePermissionFromSet(string $permissionSetId, string $permissionId): bool
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');
        $this->requireValidUuid($permissionId, 'permission_id');

        return $this->setPermissionGateway->deleteByKey($permissionSetId, $permissionId);
    }

    // ----------------------------------------------------------------
    // PermissionSetMembers (user-to-set memberships)
    // ----------------------------------------------------------------

    /**
     * Returns all user memberships for a set.
     *
     * @param string $permissionSetId The UUID of the set.
     * @return array<int, array<string, mixed>> The list of memberships.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function getMembersBySetId(string $permissionSetId): array
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        return $this->setMemberGateway->findBySetId($permissionSetId);
    }

    /**
     * Returns a single user-to-set membership.
     *
     * @param string $permissionSetId The UUID of the set.
     * @param string $userId          The UUID of the user.
     * @return array<string, mixed>|null The membership or null when not found.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function getSetMember(string $permissionSetId, string $userId): ?array
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');
        $this->requireValidUuid($userId, 'user_id');

        return $this->setMemberGateway->findByKey($permissionSetId, $userId);
    }

    /**
     * Adds a user to a permission set.
     *
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $userId          The UUID of the user.
     * @param string      $accessGranted   The date-time access was granted.
     * @param string      $accessExpiry    The date-time access expires.
     * @param bool        $hasAccess       Whether access is currently active.
     * @param string|null $notes           Optional notes.
     * @return array<string, mixed> The newly created membership.
     * @throws InvalidArgumentException When validation or existence checks fail.
     */
    public function addMemberToSet(
        string $permissionSetId,
        string $userId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): array {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');
        $this->requireValidUuid($userId, 'user_id');

        if ($this->setGateway->findById($permissionSetId) === null) {
            throw new InvalidArgumentException('The specified permission set does not exist.');
        }

        if ($this->userGateway->findById($userId) === null) {
            throw new InvalidArgumentException('The specified user does not exist.');
        }

        $this->setMemberGateway->insert($permissionSetId, $userId, $accessGranted, $accessExpiry, $hasAccess, $notes);

        return $this->setMemberGateway->findByKey($permissionSetId, $userId);
    }

    /**
     * Updates a user-to-set membership.
     *
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $userId          The UUID of the user.
     * @param string      $accessGranted   The new grant date-time.
     * @param string      $accessExpiry    The new expiry date-time.
     * @param bool        $hasAccess       Whether access is currently active.
     * @param string|null $notes           The new notes.
     * @return array<string, mixed>|null The updated record or null when not found.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function updateSetMember(
        string $permissionSetId,
        string $userId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): ?array {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');
        $this->requireValidUuid($userId, 'user_id');

        $existing = $this->setMemberGateway->findByKey($permissionSetId, $userId);
        if ($existing === null) {
            return null;
        }

        $this->setMemberGateway->update($permissionSetId, $userId, $accessGranted, $accessExpiry, $hasAccess, $notes);

        return $this->setMemberGateway->findByKey($permissionSetId, $userId);
    }

    /**
     * Removes a user from a permission set.
     *
     * @param string $permissionSetId The UUID of the set.
     * @param string $userId          The UUID of the user.
     * @return bool True when the membership was deleted.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function removeMemberFromSet(string $permissionSetId, string $userId): bool
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');
        $this->requireValidUuid($userId, 'user_id');

        return $this->setMemberGateway->deleteByKey($permissionSetId, $userId);
    }

    // ----------------------------------------------------------------
    // PermissionSetUserGroups (group-to-set memberships)
    // ----------------------------------------------------------------

    /**
     * Returns all group memberships for a set.
     *
     * @param string $permissionSetId The UUID of the set.
     * @return array<int, array<string, mixed>> The list of memberships.
     * @throws InvalidArgumentException When the identifier is not a valid UUID.
     */
    public function getGroupMembersBySetId(string $permissionSetId): array
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        return $this->setGroupMemberGateway->findBySetId($permissionSetId);
    }

    /**
     * Returns a single group-to-set membership.
     *
     * @param string $userGroupId     The UUID of the group.
     * @param string $permissionSetId The UUID of the set.
     * @return array<string, mixed>|null The membership or null when not found.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function getSetGroupMember(string $userGroupId, string $permissionSetId): ?array
    {
        $this->requireValidUuid($userGroupId, 'user_group_id');
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        return $this->setGroupMemberGateway->findByKey($userGroupId, $permissionSetId);
    }

    /**
     * Adds a group to a permission set.
     *
     * @param string      $userGroupId     The UUID of the group.
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $accessGranted   The date-time access was granted.
     * @param string      $accessExpiry    The date-time access expires.
     * @param bool        $hasAccess       Whether access is currently active.
     * @param string|null $notes           Optional notes.
     * @return array<string, mixed> The newly created membership.
     * @throws InvalidArgumentException When validation or existence checks fail.
     */
    public function addGroupToSet(
        string $userGroupId,
        string $permissionSetId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): array {
        $this->requireValidUuid($userGroupId, 'user_group_id');
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        if ($this->groupGateway->findById($userGroupId) === null) {
            throw new InvalidArgumentException('The specified user group does not exist.');
        }

        if ($this->setGateway->findById($permissionSetId) === null) {
            throw new InvalidArgumentException('The specified permission set does not exist.');
        }

        $this->setGroupMemberGateway->insert(
            $userGroupId,
            $permissionSetId,
            $accessGranted,
            $accessExpiry,
            $hasAccess,
            $notes
        );

        return $this->setGroupMemberGateway->findByKey($userGroupId, $permissionSetId);
    }

    /**
     * Updates a group-to-set membership.
     *
     * @param string      $userGroupId     The UUID of the group.
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $accessGranted   The new grant date-time.
     * @param string      $accessExpiry    The new expiry date-time.
     * @param bool        $hasAccess       Whether access is currently active.
     * @param string|null $notes           The new notes.
     * @return array<string, mixed>|null The updated record or null when not found.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function updateSetGroupMember(
        string $userGroupId,
        string $permissionSetId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): ?array {
        $this->requireValidUuid($userGroupId, 'user_group_id');
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        $existing = $this->setGroupMemberGateway->findByKey($userGroupId, $permissionSetId);
        if ($existing === null) {
            return null;
        }

        $this->setGroupMemberGateway->update(
            $userGroupId,
            $permissionSetId,
            $accessGranted,
            $accessExpiry,
            $hasAccess,
            $notes
        );

        return $this->setGroupMemberGateway->findByKey($userGroupId, $permissionSetId);
    }

    /**
     * Removes a group from a permission set.
     *
     * @param string $userGroupId     The UUID of the group.
     * @param string $permissionSetId The UUID of the set.
     * @return bool True when the membership was deleted.
     * @throws InvalidArgumentException When a key is not a valid UUID.
     */
    public function removeGroupFromSet(string $userGroupId, string $permissionSetId): bool
    {
        $this->requireValidUuid($userGroupId, 'user_group_id');
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        return $this->setGroupMemberGateway->deleteByKey($userGroupId, $permissionSetId);
    }
}
