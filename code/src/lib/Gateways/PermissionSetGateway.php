<?php

declare(strict_types=1);

/**
 * Data gateway for the PermissionSets table.
 *
 * Handles CRUD operations and queries for permission set master records.
 */

namespace App\Gateways;

use Lampfire\Gateways\AbstractDatabaseGateway;

class PermissionSetGateway extends AbstractDatabaseGateway
{
    /**
     * {@inheritDoc}
     */
    protected function getTableName(): string
    {
        return 'PermissionSets';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPrimaryKeyColumns(): array
    {
        return ['permission_set_id'];
    }

    /**
     * Returns all permission sets ordered by title.
     *
     * @return array<int, array<string, mixed>> The list of permission sets.
     */
    public function findAll(): array
    {
        $statement = $this->pdo->query(
            'SELECT permission_set_id, permission_set_token, title, notes,
                    created_at, updated_at
             FROM PermissionSets
             ORDER BY title ASC'
        );

        return $statement->fetchAll();
    }

    /**
     * Returns a single permission set by identifier.
     *
     * @param string $permissionSetId The UUID primary key.
     * @return array<string, mixed>|null The row or null when not found.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function findById(string $permissionSetId): ?array
    {
        return $this->fetchByPrimaryKey(['permission_set_id' => $permissionSetId]);
    }

    /**
     * Inserts a new permission set.
     *
     * @param string      $permissionSetId    The pre-generated UUID.
     * @param string      $permissionSetToken The programmatic token string.
     * @param string      $title              The human-readable title.
     * @param string|null $notes              Optional notes.
     * @return bool True when the insert succeeds.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function insert(
        string $permissionSetId,
        string $permissionSetToken,
        string $title,
        ?string $notes = null
    ): bool {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        $statement = $this->pdo->prepare(
            'INSERT INTO PermissionSets (permission_set_id, permission_set_token, title, notes)
             VALUES (:permission_set_id, :permission_set_token, :title, :notes)'
        );

        return $statement->execute([
            'permission_set_id'    => $permissionSetId,
            'permission_set_token' => $permissionSetToken,
            'title'                => $title,
            'notes'                => $notes,
        ]);
    }

    /**
     * Updates an existing permission set.
     *
     * @param string      $permissionSetId    The UUID of the set.
     * @param string      $permissionSetToken The new token string.
     * @param string      $title              The new title.
     * @param string|null $notes              The new notes.
     * @return bool True when at least one row was updated.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function update(
        string $permissionSetId,
        string $permissionSetToken,
        string $title,
        ?string $notes = null
    ): bool {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        $statement = $this->pdo->prepare(
            'UPDATE PermissionSets
             SET permission_set_token = :permission_set_token,
                 title                = :title,
                 notes                = :notes
             WHERE permission_set_id = :permission_set_id'
        );

        $statement->execute([
            'permission_set_id'    => $permissionSetId,
            'permission_set_token' => $permissionSetToken,
            'title'                => $title,
            'notes'                => $notes,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Deletes a permission set by primary key.
     *
     * @param string $permissionSetId The UUID of the set.
     * @return bool True when a row was deleted.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function delete(string $permissionSetId): bool
    {
        return $this->deleteByPrimaryKey(['permission_set_id' => $permissionSetId]);
    }

    /**
     * Checks whether a permission set token already exists.
     *
     * @param string      $token     The token to check.
     * @param string|null $excludeId An optional permission_set_id to exclude.
     * @return bool True when the token is already in use.
     */
    public function tokenExists(string $token, ?string $excludeId = null): bool
    {
        $excludeKey = is_string($excludeId) ? ['permission_set_id' => $excludeId] : null;

        return $this->valueExists('permission_set_token', $token, $excludeKey);
    }
}
