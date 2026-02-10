<?php

declare(strict_types=1);

/**
 * Data gateway for the UserGroups table.
 *
 * Handles CRUD operations and queries for user group master records.
 */

namespace App\Gateways;

use Lampfire\Gateways\AbstractDatabaseGateway;

class UserGroupGateway extends AbstractDatabaseGateway
{
    /**
     * {@inheritDoc}
     */
    protected function getTableName(): string
    {
        return 'UserGroups';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPrimaryKeyColumns(): array
    {
        return ['user_group_id'];
    }

    /**
     * Returns all user groups ordered by name.
     *
     * @return array<int, array<string, mixed>> The list of user groups.
     */
    public function findAll(): array
    {
        $statement = $this->pdo->query(
            'SELECT user_group_id, group_name, description, created_at, updated_at
             FROM UserGroups
             ORDER BY group_name ASC'
        );

        return $statement->fetchAll();
    }

    /**
     * Returns a single user group by identifier.
     *
     * @param string $userGroupId The UUID primary key.
     * @return array<string, mixed>|null The row or null when not found.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function findById(string $userGroupId): ?array
    {
        return $this->fetchByPrimaryKey(['user_group_id' => $userGroupId]);
    }

    /**
     * Inserts a new user group.
     *
     * @param string      $userGroupId The pre-generated UUID.
     * @param string      $groupName   The group display name.
     * @param string|null $description An optional description.
     * @return bool True when the insert succeeds.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function insert(string $userGroupId, string $groupName, ?string $description = null): bool
    {
        $this->requireValidUuid($userGroupId, 'user_group_id');

        $statement = $this->pdo->prepare(
            'INSERT INTO UserGroups (user_group_id, group_name, description)
             VALUES (:user_group_id, :group_name, :description)'
        );

        return $statement->execute([
            'user_group_id' => $userGroupId,
            'group_name'    => $groupName,
            'description'   => $description,
        ]);
    }

    /**
     * Updates an existing user group.
     *
     * @param string      $userGroupId The UUID of the group.
     * @param string      $groupName   The new display name.
     * @param string|null $description The new description.
     * @return bool True when at least one row was updated.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function update(string $userGroupId, string $groupName, ?string $description = null): bool
    {
        $this->requireValidUuid($userGroupId, 'user_group_id');

        $statement = $this->pdo->prepare(
            'UPDATE UserGroups
             SET group_name = :group_name, description = :description
             WHERE user_group_id = :user_group_id'
        );

        $statement->execute([
            'user_group_id' => $userGroupId,
            'group_name'    => $groupName,
            'description'   => $description,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Deletes a user group by primary key.
     *
     * @param string $userGroupId The UUID of the group.
     * @return bool True when a row was deleted.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function delete(string $userGroupId): bool
    {
        return $this->deleteByPrimaryKey(['user_group_id' => $userGroupId]);
    }
}
