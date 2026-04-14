<?php

declare(strict_types=1);

namespace Lampfire\Records;

/**
 * PermissionSetMemberRecord represents a user-to-set membership.
 */
class PermissionSetMemberRecord extends AbstractDatabaseRecord
{
    use DatabaseRecordConstructorTrait;

    protected ?string $query = null;
    protected array $primaryKeys = ['permission_set_id', 'user_id'];
    protected array $fields = [
        'permission_set_id',
        'user_id',
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
        $this->query = 'SELECT permission_set_id, user_id, access_granted, access_expiry, has_access, notes,
                               created_at, updated_at
                        FROM PermissionSetMembers
                        WHERE permission_set_id = :permission_set_id
                        ORDER BY created_at DESC';

        $statement = $this->connection->getConnection()->prepare($this->query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare permission set member list query.');
        }

        $success = $statement->execute(['permission_set_id' => $permissionSetId]);
        if ($success !== true) {
            throw new \RuntimeException('Failed to execute permission set member list query.');
        }

        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            throw new \RuntimeException('Failed to fetch permission set member list rows.');
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByUserId(string $userId): array
    {
        $this->query = 'SELECT permission_set_id, user_id, access_granted, access_expiry, has_access, notes,
                               created_at, updated_at
                        FROM PermissionSetMembers
                        WHERE user_id = :user_id
                        ORDER BY created_at DESC';

        $statement = $this->connection->getConnection()->prepare($this->query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare permission set member user query.');
        }

        $success = $statement->execute(['user_id' => $userId]);
        if ($success !== true) {
            throw new \RuntimeException('Failed to execute permission set member user query.');
        }

        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            throw new \RuntimeException('Failed to fetch permission set member user rows.');
        }

        return $rows;
    }
}
