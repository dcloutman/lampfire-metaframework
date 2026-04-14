<?php

declare(strict_types=1);

namespace Lampfire\Records;

/**
 * PermissionSetRecord represents a permission set entity in the PermissionSets table.
 */
class PermissionSetRecord extends AbstractDatabaseRecord
{
    use DatabaseRecordConstructorTrait;

    protected ?string $query = null;
    protected array $primaryKeys = ['permission_set_id'];
    protected array $fields = [
        'permission_set_id',
        'permission_set_token',
        'title',
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
    public function findAll(): array
    {
        $this->query = 'SELECT permission_set_id, permission_set_token, title, notes, created_at, updated_at
                        FROM PermissionSets
                        ORDER BY title ASC';

        $statement = $this->connection->getConnection()->query($this->query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to execute permission set list query.');
        }

        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            throw new \RuntimeException('Failed to fetch permission set list rows.');
        }

        return $rows;
    }

    public function tokenExists(string $token, ?string $excludeId = null): bool
    {
        $query = 'SELECT COUNT(*) FROM PermissionSets WHERE permission_set_token = :token';
        $parameters = ['token' => $token];

        if ($excludeId !== null) {
            $query .= ' AND permission_set_id != :permission_set_id';
            $parameters['permission_set_id'] = $excludeId;
        }

        $this->query = $query;

        $statement = $this->connection->getConnection()->prepare($this->query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare permission set token existence query.');
        }

        $success = $statement->execute($parameters);
        if ($success !== true) {
            throw new \RuntimeException('Failed to execute permission set token existence query.');
        }

        return (int) $statement->fetchColumn() > 0;
    }
}