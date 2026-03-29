<?php

declare(strict_types=1);

/**
 * Abstract base for database-backed gateways.
 *
 * Holds the shared PDO connection and provides reusable query helpers
 * so that concrete gateways do not duplicate boilerplate for single-row
 * fetches, existence checks, or simple deletions. All helpers support
 * composite primary keys, not just single-column keys.
 */

namespace Lampfire\Gateways;

use PDO;

abstract class AbstractDatabaseGateway extends AbstractGateway
{
    /**
     * @var PDO The shared database connection.
     */
    protected PDO $pdo;

    /**
     * Creates the database gateway.
     *
     * @param PDO $pdo The database connection.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Returns the database table name for this gateway.
     *
     * @return string The table name.
     */
    abstract protected function getTableName(): string;

    /**
     * Returns the column names that form the primary key.
     *
     * Tables with a single-column key return a one-element array.
     * Tables with composite keys return two or more elements.
     *
     * @return array<int, string> The list of primary key columns.
     */
    abstract protected function getPrimaryKeyColumns(): array;

    /**
     * Validates that the primary key names provided match those declared by the gateway.
     *
     * @param array<string, string> $primaryKeysToValues Column-name-to-value pairs.
     * @return void
     * @throws \InvalidArgumentException When a required column is missing
     */
    protected function validatePrimaryKeyValues(array $primaryKeysToValues): void
    {
        $primaryKeyNames = $this->getPrimaryKeyColumns();
        if (
            count($primaryKeyNames) !== count($primaryKeysToValues) ||
            sort($primaryKeyNames) !== sort(array_keys($primaryKeysToValues))
        ) {
            throw new \RuntimeException(
                'Provided primary key colums do not match the gateway\'s primary key columns.'
            );
        }
    }

    /**
     * Builds a WHERE clause fragment and a matching parameter map for the
     * declared primary key columns.
     *
     * Example return for a composite key (`user_id`, `user_group_id`):
     *   [
     *     'user_id = :user_id AND user_group_id = :user_group_id',
     *     ['user_id' => '...', 'user_group_id' => '...']
     *   ]
     *
     * @param array<string, string> $keyValues Column-name-to-value pairs.
     * @return array{0: string, 1: array<string, string>} The SQL fragment
     *         and its named parameters.
     */
    protected function buildPrimaryKeyWhere(array $keyValues): array
    {
        $this->validatePrimaryKeyValues($keyValues);

        $clauses = [];
        $params  = [];

        foreach ($this->getPrimaryKeyColumns() as $column) {
            $clauses[]       = sprintf('%s = :%s', $column, $column);
            $params[$column] = $keyValues[$column];
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Fetches a single row by its full primary key.
     *
     * Pass an associative array mapping every primary key column to its
     * value. For a single-column key this is simply
     * `['user_id' => $userId]`; for a composite key every column in the
     * key must be present.
     *
     * @param array<string, string> $keyValues  Column-name-to-value pairs.
     * @param string                $columnList The columns to select.
     * @return array<string, mixed>|null The row or null when not found.
     * @throws \InvalidArgumentException When a key column is missing or
     *                                   a value is not a valid UUID.
     */
    protected function fetchByPrimaryKey(array $keyValues, string $columnList = '*'): ?array
    {
        [$whereClause, $params] = $this->buildPrimaryKeyWhere($keyValues);

        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s',
            $columnList,
            $this->getTableName(),
            $whereClause
        );

        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute($params);
        if ($success === false) {
            return null;
        }

        $row = $statement->fetch();

        return ($row === false) ? null : $row;
    }

    /**
     * Deletes a single row by its full primary key.
     *
     * @param array<string, string> $keyValues Column-name-to-value pairs.
     * @return bool True when a row was deleted.
     * @throws \InvalidArgumentException When a key column is missing or a value is not a valid UUID.
     * @throws \RuntimeException When the SQL statement fails to prepare or execute.
     */
    protected function deleteByPrimaryKey(array $keyValues): bool
    {
        [$whereClause, $params] = $this->buildPrimaryKeyWhere($keyValues);

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->getTableName(),
            $whereClause
        );

        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }
        
        $success = $statement->execute($params);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->rowCount() > 0;
    }
}
