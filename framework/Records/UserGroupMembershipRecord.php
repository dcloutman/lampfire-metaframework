<?php
declare(strict_types=1);

namespace Lampfire\Records;

/**
 * UserGroupMembershipRecord represents the membership of a user in a user group.
 *
 */
class UserGroupMembershipRecord extends AbstractDatabaseRecord
{
    protected ?string $query = null;
    protected array $primaryKeys = ['user_id', 'user_group_id'];
    protected array $fields = [
        'user_id',
        'user_group_id',
        'access_granted',
        'access_expiry',
        'has_access',
        'created_at',
        'updated_at',
    ];
    protected array $hiddenFields =[];
    protected array $readOnlyFields = [
        'user_id',
        'user_group_id',
        'created_at',
        'updated_at',
    ];

    use DatabaseRecordConstructorTrait;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByUserId(string $userId): array
    {
        $this->query = 'SELECT user_id, user_group_id, access_granted, access_expiry, has_access,
                               created_at, updated_at
                        FROM UserGroupMemberships
                        WHERE user_id = :user_id
                        ORDER BY created_at DESC';

        $statement = $this->connection->getConnection()->prepare($this->query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare user group membership user query.');
        }

        $success = $statement->execute(['user_id' => $userId]);
        if ($success !== true) {
            throw new \RuntimeException('Failed to execute user group membership user query.');
        }

        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            throw new \RuntimeException('Failed to fetch user group membership user rows.');
        }

        return $rows;
    }
}