<?php

declare(strict_types=1);

namespace Lampfire\Records;

/**
 * PermissionSetPermissionRecord represents a permission-to-set association.
 */
class PermissionSetPermissionRecord extends AbstractDatabaseRecord
{
    use DatabaseRecordConstructorTrait;

    protected ?string $query = null;
    protected array $primaryKeys = ['permission_set_id', 'permission_id'];
    protected array $fields = [
        'permission_set_id',
        'permission_id',
        'notes',
        'created_at',
        'updated_at',
    ];
    protected array $hiddenFields = [];
    protected array $readOnlyFields = [
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findBySetId(string $permissionSetId): array
    {
        $this->query = 'SELECT permission_set_id, permission_id, notes, created_at, updated_at
                        FROM PermissionSetPermissions
                        WHERE permission_set_id = :permission_set_id
                        ORDER BY created_at DESC';

        $statement = $this->connection->getConnection()->prepare($this->query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare permission set permission list query.');
        }

        $success = $statement->execute(['permission_set_id' => $permissionSetId]);
        if ($success !== true) {
            throw new \RuntimeException('Failed to execute permission set permission list query.');
        }

        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            throw new \RuntimeException('Failed to fetch permission set permission list rows.');
        }

        return $rows;
    }
}
