<?php

declare(strict_types=1);

/**
 * Data gateway for the PermissionSetPermissions table.
 *
 * Manages the many-to-many relationship between PermissionSets and
 * Permissions. This table uses a composite primary key.
 */

namespace Lampfire\Gateways;


class PermissionSetPermissionGateway extends AbstractDatabaseGateway
{
    /**
     * {@inheritDoc}
     */
    protected function getTableName(): string
    {
        return 'PermissionSetPermissions';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPrimaryKeyColumns(): array
    {
        return ['permission_set_id', 'permission_id'];
    }

    /**
     * Returns all permissions assigned to a given permission set.
     *
     * @param string $permissionSetId The UUID of the set.
     * @return array<int, array<string, mixed>> The list of associations.
     * @throws \InvalidArgumentException When the key is not a valid UUID.
     */
    public function findBySetId(string $permissionSetId): array
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');

        $statement = $this->pdo->prepare(
            'SELECT permission_set_id, permission_id, notes, created_at, updated_at
             FROM PermissionSetPermissions
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
     * Returns a single association by its composite key.
     *
     * @param string $permissionSetId The UUID of the set.
     * @param string $permissionId    The UUID of the permission.
     * @return array<string, mixed>|null The row or null when not found.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function findByKey(string $permissionSetId, string $permissionId): ?array
    {
        return $this->fetchByPrimaryKey([
            'permission_set_id' => $permissionSetId,
            'permission_id'     => $permissionId,
        ]);
    }

    /**
     * Inserts a new association between a permission set and a permission.
     *
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $permissionId    The UUID of the permission.
     * @param string|null $notes           Optional notes.
     * @return bool True when the insert succeeds.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function insert(string $permissionSetId, string $permissionId, ?string $notes = null): bool
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');
        $this->requireValidUuid($permissionId, 'permission_id');

        $statement = $this->pdo->prepare(
            'INSERT INTO PermissionSetPermissions (permission_set_id, permission_id, notes)
             VALUES (:permission_set_id, :permission_id, :notes)'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'permission_set_id' => $permissionSetId,
            'permission_id'     => $permissionId,
            'notes'             => $notes,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return true;
    }

    /**
     * Updates the notes on an existing association.
     *
     * @param string      $permissionSetId The UUID of the set.
     * @param string      $permissionId    The UUID of the permission.
     * @param string|null $notes           The new notes.
     * @return bool True when at least one row was updated.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function update(string $permissionSetId, string $permissionId, ?string $notes = null): bool
    {
        $this->requireValidUuid($permissionSetId, 'permission_set_id');
        $this->requireValidUuid($permissionId, 'permission_id');

        $statement = $this->pdo->prepare(
            'UPDATE PermissionSetPermissions
             SET notes = :notes
             WHERE permission_set_id = :permission_set_id
               AND permission_id = :permission_id'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'permission_set_id' => $permissionSetId,
            'permission_id'     => $permissionId,
            'notes'             => $notes,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->rowCount() > 0;
    }

    /**
     * Deletes an association by its composite key.
     *
     * @param string $permissionSetId The UUID of the set.
     * @param string $permissionId    The UUID of the permission.
     * @return bool True when a row was deleted.
     * @throws \InvalidArgumentException When a key is not a valid UUID.
     */
    public function deleteByKey(string $permissionSetId, string $permissionId): bool
    {
        return $this->deleteByPrimaryKey([
            'permission_set_id' => $permissionSetId,
            'permission_id'     => $permissionId,
        ]);
    }
}
