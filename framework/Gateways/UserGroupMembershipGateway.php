<?php

declare(strict_types=1);

/**
 * Data gateway for the UserGroupMemberships table.
 *
 * Handles CRUD for the many-to-many relationship between Users and
 * UserGroups. This table uses a composite primary key.
 */

namespace Lampfire\Gateways;


class UserGroupMembershipGateway extends AbstractDatabaseGateway
{
    /**
     * {@inheritDoc}
     */
    protected function getTableName(): string
    {
        return 'UserGroupMemberships';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPrimaryKeyColumns(): array
    {
        return ['user_id', 'user_group_id'];
    }

    /**
     * Returns all memberships for a given user group.
     *
     * @param string $userGroupId The UUID of the group.
     * @return array<int, array<string, mixed>> The list of memberships.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function findByGroupId(string $userGroupId): array
    {
        $this->requireValidUuid($userGroupId, 'user_group_id');

        $statement = $this->pdo->prepare(
            'SELECT user_id, user_group_id, access_granted, access_expiry,
                    has_access, created_at, updated_at
             FROM UserGroupMemberships
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
     * Returns all memberships for a given user.
     *
     * @param string $userId The UUID of the user.
     * @return array<int, array<string, mixed>> The list of memberships.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function findByUserId(string $userId): array
    {
        $this->requireValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'SELECT user_id, user_group_id, access_granted, access_expiry,
                    has_access, created_at, updated_at
             FROM UserGroupMemberships
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
     * @param string $userId      The UUID of the user.
     * @param string $userGroupId The UUID of the group.
     * @return array<string, mixed>|null The row or null when not found.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function findByKey(string $userId, string $userGroupId): ?array
    {
        return $this->fetchByPrimaryKey([
            'user_id'       => $userId,
            'user_group_id' => $userGroupId,
        ]);
    }

    /**
     * Inserts a new membership record.
     *
     * @param string $userId        The UUID of the user.
     * @param string $userGroupId   The UUID of the group.
     * @param string $accessGranted The date-time access was granted.
     * @param string $accessExpiry  The date-time access expires.
     * @param bool   $hasAccess     Whether access is currently active.
     * @return bool True when the insert succeeds.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function insert(
        string $userId,
        string $userGroupId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess
    ): bool {
        $this->requireValidUuid($userId, 'user_id');
        $this->requireValidUuid($userGroupId, 'user_group_id');

        $statement = $this->pdo->prepare(
            'INSERT INTO UserGroupMemberships
                (user_id, user_group_id, access_granted, access_expiry, has_access)
             VALUES (:user_id, :user_group_id, :access_granted, :access_expiry, :has_access)'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'user_id'        => $userId,
            'user_group_id'  => $userGroupId,
            'access_granted' => $accessGranted,
            'access_expiry'  => $accessExpiry,
            'has_access'     => $hasAccess ? 1 : 0,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return true;
    }

    /**
     * Updates an existing membership record.
     *
     * @param string $userId        The UUID of the user.
     * @param string $userGroupId   The UUID of the group.
     * @param string $accessGranted The new grant date-time.
     * @param string $accessExpiry  The new expiry date-time.
     * @param bool   $hasAccess     Whether access is currently active.
     * @return bool True when at least one row was updated.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function update(
        string $userId,
        string $userGroupId,
        string $accessGranted,
        string $accessExpiry,
        bool $hasAccess
    ): bool {
        $this->requireValidUuid($userId, 'user_id');
        $this->requireValidUuid($userGroupId, 'user_group_id');

        $statement = $this->pdo->prepare(
            'UPDATE UserGroupMemberships
             SET access_granted = :access_granted,
                 access_expiry  = :access_expiry,
                 has_access     = :has_access
             WHERE user_id = :user_id AND user_group_id = :user_group_id'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'user_id'        => $userId,
            'user_group_id'  => $userGroupId,
            'access_granted' => $accessGranted,
            'access_expiry'  => $accessExpiry,
            'has_access'     => $hasAccess ? 1 : 0,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->rowCount() > 0;
    }

    /**
     * Deletes a membership by its composite key.
     *
     * @param string $userId      The UUID of the user.
     * @param string $userGroupId The UUID of the group.
     * @return bool True when a row was deleted.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function deleteByKey(string $userId, string $userGroupId): bool
    {
        return $this->deleteByPrimaryKey([
            'user_id'       => $userId,
            'user_group_id' => $userGroupId,
        ]);
    }
}
