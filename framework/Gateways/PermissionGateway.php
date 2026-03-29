<?php

declare(strict_types=1);

/**
 * Data gateway for the Permissions table.
 *
 * Handles CRUD operations and queries for individual permission records.
 */

namespace Lampfire\Gateways;

use Lampfire\Utilities\Enforcers;


class PermissionGateway extends AbstractDatabaseGateway
{
    /**
     * {@inheritDoc}
     */
    protected function getTableName(): string
    {
        return 'Permissions';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPrimaryKeyColumns(): array
    {
        return ['permission_id'];
    }

    /**
     * Returns all permissions ordered by title.
     *
     * @return array<int, array<string, mixed>> The list of permissions.
     */
    public function findAll(): array
    {
        $statement = $this->pdo->query(
            'SELECT permission_id, permission_token, permission_title, notes,
                    created_at, updated_at
             FROM Permissions
             ORDER BY permission_title ASC'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute();
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->fetchAll();
    }

    /**
     * Returns a single permission by identifier.
     *
     * @param string $permissionId The UUID primary key.
     * @return array<string, mixed>|null The row or null when not found.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function findById(string $permissionId): ?array
    {
        return $this->fetchByPrimaryKey(['permission_id' => $permissionId]);
    }

    /**
     * Inserts a new permission.
     *
     * @param string $permissionId The pre-generated UUID.
     * @param string $permissionToken The programmatic token string.
     * @param string $permissionTitle The human-readable title.
     * @param string $notes Optional notes.
     * @return bool True when the insert succeeds.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function insert(
        string $permissionId,
        string $permissionToken,
        string $permissionTitle,
        string $notes = ''
    ): bool {
        Enforcers::enforceValidUuid($permissionId, 'permission_id');

        $statement = $this->pdo->prepare(
            'INSERT INTO Permissions (permission_id, permission_token, permission_title, notes)
             VALUES (:permission_id, :permission_token, :permission_title, :notes)'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'permission_id'    => $permissionId,
            'permission_token' => $permissionToken,
            'permission_title' => $permissionTitle,
            'notes'            => $notes,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $success;
    }

    /**
     * Updates an existing permission.
     *
     * @param string      $permissionId    The UUID of the permission.
     * @param string      $permissionToken The new token string.
     * @param string      $permissionTitle The new title.
     * @param string|null $notes           The new notes.
     * @return bool True when at least one row was updated.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function update(
        string $permissionId,
        string $permissionToken,
        string $permissionTitle,
        ?string $notes = null
    ): bool {
        Enforcers::enforceValidUuid($permissionId, 'permission_id');

        $statement = $this->pdo->prepare(
            'UPDATE Permissions
             SET permission_token = :permission_token,
                 permission_title = :permission_title,
                 notes            = :notes
             WHERE permission_id = :permission_id'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'permission_id'    => $permissionId,
            'permission_token' => $permissionToken,
            'permission_title' => $permissionTitle,
            'notes'            => $notes,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->rowCount() > 0;
    }

    /**
     * Deletes a permission by primary key.
     *
     * @param string $permissionId The UUID of the permission.
     * @return bool True when a row was deleted.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function delete(string $permissionId): bool
    {
        return $this->deleteByPrimaryKey(['permission_id' => $permissionId]);
    }

    /**
     * Checks whether a permission token already exists.
     *
     * @param string      $permissionToken The token to check.
     * @param string|null $excludeId       An optional permission_id to exclude.
     * @return bool True when the token is already in use.
     */
    public function tokenExists(string $permissionToken, ?string $excludeId = null): bool
    {
        $query = 'SELECT COUNT(*) FROM Permissions WHERE permission_token = :permission_token';
        $params = ['permission_token' => $permissionToken];

        if (!is_null($excludeId)) {
            $query .= ' AND permission_id != :permission_id';
            $params['permission_id'] = $excludeId;
        }

        $statement = $this->pdo->prepare($query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute($params);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return (int)$statement->fetchColumn() > 0;
    }
}
