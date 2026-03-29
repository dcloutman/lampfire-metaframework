<?php

declare(strict_types=1);

/**
 * Data gateway for the PermissionSetMembers table.
 *
 * Manages the many-to-many relationship between PermissionSets and
 * individual Users. This table uses a composite primary key.
 */

namespace Lampfire\Gateways;

use Lampfire\Utilities\Enforcers;


class PermissionSetMemberGateway extends AbstractDatabaseGateway
{
    /**
     * {@inheritDoc}
     */
    protected function getTableName(): string
    {
        return 'PermissionSetMembers';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPrimaryKeyColumns(): array
    {
        return ['permission_set_id', 'user_id'];
    }

    /**
     * Returns all user memberships for a given permission set.
     *
     * @param string $permissionSetId The UUID of the set.
     * @return array<int, array<string, mixed>> The list of memberships.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function findBySetId(string $permissionSetId): array
    {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');

        $statement = $this->pdo->prepare(
            'SELECT permission_set_id, user_id, access_granted, access_expiry,
                    has_access, notes, created_at, updated_at
             FROM PermissionSetMembers
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
     * Returns all permission set memberships for a given user.
     *
     * @param string $userId The UUID of the user.
     * @return array<int, array<string, mixed>> The list of memberships.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function findByUserId(string $userId): array
    {
        Enforcers::enforceValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'SELECT permission_set_id, user_id, access_granted, access_expiry,
                    has_access, notes, created_at, updated_at
             FROM PermissionSetMembers
             WHERE user_id = :user_id
             ORDER BY created_at DESC'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute(['user_id' => $userId]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->fetchAll();
    }

    /**
     * Returns a single membership by its composite key.
     *
     * @param string $permissionSetId The UUID of the set.
     * @param string $userId          The UUID of the user.
     * @return array<string, mixed>|null The row or null when not found.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function findByKey(string $permissionSetId, string $userId): ?array
    {
        return $this->fetchByPrimaryKey([
            'permission_set_id' => $permissionSetId,
            'user_id'           => $userId,
        ]);
    }

    /**
     * Inserts a new user-to-permission-set membership.
     *
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $userId          The UUID of the user.
     * @param string      $accessGranted   The date-time access was granted.
     * @param string      $accessExpiry    The date-time access expires.
     * @param bool        $hasAccess       Whether access is currently active.
     * @param string|null $notes           Optional notes.
     * @return bool True when the insert succeeds.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function insert(
        string $permissionSetId,
        string $userId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): bool {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');
        Enforcers::enforceValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'INSERT INTO PermissionSetMembers
                (permission_set_id, user_id, access_granted, access_expiry, has_access, notes)
             VALUES (:permission_set_id, :user_id, :access_granted, :access_expiry, :has_access, :notes)'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'permission_set_id' => $permissionSetId,
            'user_id'           => $userId,
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
     * Updates an existing user-to-permission-set membership.
     *
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $userId          The UUID of the user.
     * @param string      $accessGranted   The new grant date-time.
     * @param string      $accessExpiry    The new expiry date-time.
     * @param bool        $hasAccess       Whether access is currently active.
     * @param string|null $notes           The new notes.
     * @return bool True when at least one row was updated.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function update(
        string $permissionSetId,
        string $userId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess,
        ?string $notes = null
    ): bool {
        Enforcers::enforceValidUuid($permissionSetId, 'permission_set_id');
        Enforcers::enforceValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'UPDATE PermissionSetMembers
             SET access_granted = :access_granted,
                 access_expiry  = :access_expiry,
                 has_access     = :has_access,
                 notes          = :notes
             WHERE permission_set_id = :permission_set_id
               AND user_id = :user_id'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'permission_set_id' => $permissionSetId,
            'user_id'           => $userId,
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
     * @param string $permissionSetId The UUID of the set.
     * @param string $userId          The UUID of the user.
     * @return bool True when a row was deleted.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function deleteByKey(string $permissionSetId, string $userId): bool
    {
        return $this->deleteByPrimaryKey([
            'permission_set_id' => $permissionSetId,
            'user_id'           => $userId,
        ]);
    }
}
