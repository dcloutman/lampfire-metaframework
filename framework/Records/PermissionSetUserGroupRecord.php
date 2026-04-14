<?php

declare(strict_types=1);

namespace Lampfire\Records;

/**
 * PermissionSetUserGroupRecord represents a group-to-set membership.
 */
class PermissionSetUserGroupRecord extends AbstractDatabaseRecord
{
    use DatabaseRecordConstructorTrait;

    protected ?string $query = null;
    protected array $primaryKeys = ['user_group_id', 'permission_set_id'];
    protected array $fields = [
        'user_group_id',
        'permission_set_id',
        'access_granted',
        'access_expiry',
        'has_access',
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
        $this->query = 'SELECT user_group_id, permission_set_id, access_granted, access_expiry, has_access, notes,
                               created_at, updated_at
                        FROM PermissionSetUserGroups
                        WHERE permission_set_id = :permission_set_id
                        ORDER BY created_at DESC';

        $statement = $this->connection->getConnection()->prepare($this->query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare permission set user group list query.');
        }

        $success = $statement->execute(['permission_set_id' => $permissionSetId]);
        if ($success !== true) {
            throw new \RuntimeException('Failed to execute permission set user group list query.');
        }

        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            throw new \RuntimeException('Failed to fetch permission set user group list rows.');
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByGroupId(string $userGroupId): array
    {
        $this->query = 'SELECT user_group_id, permission_set_id, access_granted, access_expiry, has_access, notes,
                               created_at, updated_at
                        FROM PermissionSetUserGroups
                        WHERE user_group_id = :user_group_id
                        ORDER BY created_at DESC';

        $statement = $this->connection->getConnection()->prepare($this->query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare permission set user group query by group.');
        }

        $success = $statement->execute(['user_group_id' => $userGroupId]);
        if ($success !== true) {
            throw new \RuntimeException('Failed to execute permission set user group query by group.');
        }

        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            throw new \RuntimeException('Failed to fetch permission set user group rows by group.');
        }

        return $rows;
    }
}
