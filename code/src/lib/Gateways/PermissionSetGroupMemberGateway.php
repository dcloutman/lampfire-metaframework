<?php

declare(strict_types=1);

/**
 * Data gateway for the PermissionSetGroupMembers table.
 *
 * Manages the many-to-many relationship between PermissionSets and
 * UserGroups. This table uses a composite primary key.
 */

namespace App\Gateways;

use Lampfire\Gateways\AbstractDatabaseGateway;

class PermissionSetGroupMemberGateway extends AbstractDatabaseGateway
{
    /**
     * {@inheritDoc}
     */
    protected function getTableName(): string
    {
        return 'PermissionSetGroupMembers';
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
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        $statement = $this->pdo->prepare(
            'SELECT user_group_id, permission_set_id, access_granted, access_expiry,
                    has_access, notes, created_at, updated_at
             FROM PermissionSetGroupMembers
             WHERE permission_set_id = :permission_set_id
             ORDER BY created_at DESC'
        );
        $statement->execute(['permission_set_id' => $permissionSetId]);

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
        $this->requireValidUuid($userGroupId, 'user_group_id');

        $statement = $this->pdo->prepare(
            'SELECT user_group_id, permission_set_id, access_granted, access_expiry,
                    has_access, notes, created_at, updated_at
             FROM PermissionSetGroupMembers
             WHERE user_group_id = :user_group_id
             ORDER BY created_at DESC'
        );
        $statement->execute(['user_group_id' => $userGroupId]);

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
        $this->requireValidUuid($userGroupId, 'user_group_id');
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        $statement = $this->pdo->prepare(
            'INSERT INTO PermissionSetGroupMembers
                (user_group_id, permission_set_id, access_granted, access_expiry, has_access, notes)
             VALUES (:user_group_id, :permission_set_id, :access_granted, :access_expiry, :has_access, :notes)'
        );

        return $statement->execute([
            'user_group_id'     => $userGroupId,
            'permission_set_id' => $permissionSetId,
            'access_granted'    => $accessGranted,
            'access_expiry'     => $accessExpiry,
            'has_access'        => $hasAccess ? 1 : 0,
            'notes'             => $notes,
        ]);
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
        $this->requireValidUuid($userGroupId, 'user_group_id');
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        $statement = $this->pdo->prepare(
            'UPDATE PermissionSetGroupMembers
             SET access_granted = :access_granted,
                 access_expiry  = :access_expiry,
                 has_access     = :has_access,
                 notes          = :notes
             WHERE user_group_id = :user_group_id
               AND permission_set_id = :permission_set_id'
        );

        $statement->execute([
            'user_group_id'     => $userGroupId,
            'permission_set_id' => $permissionSetId,
            'access_granted'    => $accessGranted,
            'access_expiry'     => $accessExpiry,
            'has_access'        => $hasAccess ? 1 : 0,
            'notes'             => $notes,
        ]);

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
