<?php

declare(strict_types=1);

/**
 * Data gateway for the PermissionSetUserGroups table.
 *
 * Manages the many-to-many relationship between PermissionSets and
 * UserGroups. This table uses a composite primary key.
 */

namespace Lampfire\Gateways;

use Lampfire\Utilities\Enforcers;


class PermissionSetUserGroupGateway extends AbstractDatabaseGateway
{
    /**
     * {@inheritDoc}
     */
    protected function getTableName(): string
    {
        return 'PermissionSetUserGroups';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPrimaryKeyColumns(): array
    {
        return ['user_group_id', 'permission_set_id'];
    }

    /**
     * Returns all group memberships for a given permission set.
     *
     * @param string $permissionSetId The UUID of the set.
     * @return array<int, array<string, mixed>> The list of memberships.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function findBySetId(string $permissionSetId): array
    {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');

        $statement = $this->pdo->prepare(
            'SELECT user_group_id, permission_set_id, access_granted, access_expiry,
                    has_access, notes, created_at, updated_at
             FROM PermissionSetUserGroups
             WHERE permission_set_id = :permission_set_id
             ORDER BY created_at DESC'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute(['permission_set_id' => $permissionSetId]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->fetchAll();
    }

    /**
     * Returns all permission set memberships for a given user group.
     *
     * @param string $userGroupId The UUID of the group.
     * @return array<int, array<string, mixed>> The list of memberships.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function findByGroupId(string $userGroupId): array
    {
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');

        $statement = $this->pdo->prepare(
            'SELECT user_group_id, permission_set_id, access_granted, access_expiry,
                    has_access, notes, created_at, updated_at
             FROM PermissionSetUserGroups
             WHERE user_group_id = :user_group_id
             ORDER BY created_at DESC'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute(['user_group_id' => $userGroupId]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->fetchAll();
    }

    /**
     * Returns a single membership by its composite key.
     *
     * @param string $userGroupId     The UUID of the group.
     * @param string $permissionSetId The UUID of the set.
     * @return array<string, mixed>|null The row or null when not found.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function findByKey(string $userGroupId, string $permissionSetId): ?array
    {
        return $this->fetchByPrimaryKey([
            'user_group_id'     => $userGroupId,
            'permission_set_id' => $permissionSetId,
        ]);
    }

    /**
     * Inserts a new group-to-permission-set membership.
     *
     * @param string      $userGroupId     The UUID of the group.
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $accessGranted   The date-time access was granted.
     * @param string      $accessExpiry    The date-time access expires.
     * @param bool        $hasAccess       Whether access is currently active.
     * @param string|null $notes           Optional notes.
     * @return bool True when the insert succeeds.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function insert(
        string $userGroupId,
        string $permissionSetId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): bool {
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');

        $statement = $this->pdo->prepare(
            'INSERT INTO PermissionSetUserGroups
                (user_group_id, permission_set_id, access_granted, access_expiry, has_access, notes)
             VALUES (:user_group_id, :permission_set_id, :access_granted, :access_expiry, :has_access, :notes)'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'user_group_id'     => $userGroupId,
            'permission_set_id' => $permissionSetId,
            'access_granted'    => $accessGranted,
            'access_expiry'     => $accessExpiry,
            'has_access'        => $hasAccess ? 1 : 0,
            'notes'             => $notes,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return true;
    }

    /**
     * Updates an existing group-to-permission-set membership.
     *
     * @param string      $userGroupId     The UUID of the group.
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $accessGranted   The new grant date-time.
     * @param string      $accessExpiry    The new expiry date-time.
     * @param bool        $hasAccess       Whether access is currently active.
     * @param string|null $notes           The new notes.
     * @return bool True when at least one row was updated.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function update(
        string $userGroupId,
        string $permissionSetId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): bool {
        Enforcers::enforceValidUuid($userGroupId, 'user_group_id');
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');

        $statement = $this->pdo->prepare(
            'UPDATE PermissionSetUserGroups
             SET access_granted = :access_granted,
                 access_expiry  = :access_expiry,
                 has_access     = :has_access,
                 notes          = :notes
             WHERE user_group_id = :user_group_id
               AND permission_set_id = :permission_set_id'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'user_group_id'     => $userGroupId,
            'permission_set_id' => $permissionSetId,
            'access_granted'    => $accessGranted,
            'access_expiry'     => $accessExpiry,
            'has_access'        => $hasAccess ? 1 : 0,
            'notes'             => $notes,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->rowCount() > 0;
    }

    /**
     * Deletes a membership by its composite key.
     *
     * @param string $userGroupId     The UUID of the group.
     * @param string $permissionSetId The UUID of the set.
     * @return bool True when a row was deleted.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function deleteByKey(string $userGroupId, string $permissionSetId): bool
    {
        return $this->deleteByPrimaryKey([
            'user_group_id'     => $userGroupId,
            'permission_set_id' => $permissionSetId,
        ]);
    }
}
