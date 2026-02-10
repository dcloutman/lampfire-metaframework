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
     * Returns the unqualified database table name for this gateway.
     *
     * @return string The table name.
     */
    abstract protected function getTableName(): string;

    /**
     * Returns the column names that form the primary key.
     *
     * Tables with a single-column key return a one-element array.
     * Junction tables with composite keys return two or more elements.
     *
     * @return array<int, string> The ordered list of primary key columns.
     */
    abstract protected function getPrimaryKeyColumns(): array;

    // ------------------------------------------------------------------
    //  Primary-key helpers
    // ------------------------------------------------------------------

    /**
     * Validates that every declared primary key column is present in the
     * given associative array and that each value is a valid UUID.
     *
     * @param array<string, string> $keyValues Column-name-to-value pairs.
     * @return void
     * @throws \InvalidArgumentException When a required column is missing
     *                                   or a value is not a valid UUID.
     */
    protected function validatePrimaryKeyValues(array $keyValues): void
    {
        foreach ($this->getPrimaryKeyColumns() as $column) {
            if (array_key_exists($column, $keyValues) === false) {
                throw new \InvalidArgumentException(
                    sprintf('Missing primary key column: %s.', $column)
                );
            }
            $this->requireValidUuid($keyValues[$column], $column);
        }
    }

    /**
     * Builds a WHERE clause fragment and a matching parameter map for the
     * declared primary key columns.
     *
     * Example return for a composite key (`user_id`, `user_group_id`):
     *   [
     *     'user_id = :pk_user_id AND user_group_id = :pk_user_group_id',
     *     ['pk_user_id' => '...', 'pk_user_group_id' => '...']
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
            $placeholder          = 'pk_' . $column;
            $clauses[]            = sprintf('%s = :%s', $column, $placeholder);
            $params[$placeholder] = $keyValues[$column];
        }

        return [implode(' AND ', $clauses), $params];
    }

    // ------------------------------------------------------------------
    //  Query helpers
    // ------------------------------------------------------------------

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
        $statement->execute($params);

        $row = $statement->fetch();

        return ($row === false) ? null : $row;
    }

    /**
     * Deletes a single row by its full primary key.
     *
     * @param array<string, string> $keyValues Column-name-to-value pairs.
     * @return bool True when a row was deleted.
     * @throws \InvalidArgumentException When a key column is missing or
     *                                   a value is not a valid UUID.
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
        $statement->execute($params);

        return $statement->rowCount() > 0;
    }

    /**
     * Checks whether a value already exists in a column.
     *
     * Optionally excludes a single row by primary key so that update
     * uniqueness checks do not flag the record being edited. The
     * exclusion key may be a composite key.
     *
     * @param string                     $column          The column to check.
     * @param string                     $value           The value to look for.
     * @param array<string, string>|null $excludeKeyValues An optional primary
     *                                                     key to exclude.
     * @return bool True when the value exists.
     * @throws \InvalidArgumentException When exclude key columns are
     *                                   missing or values are not valid UUIDs.
     */
    protected function valueExists(
        string $column,
        string $value,
        ?array $excludeKeyValues = null
    ): bool {
        if (is_array($excludeKeyValues) && count($excludeKeyValues) > 0) {
            [$excludeWhere, $excludeParams] = $this->buildPrimaryKeyWhere($excludeKeyValues);

            $sql = sprintf(
                'SELECT COUNT(*) FROM %s WHERE %s = :value AND NOT (%s)',
                $this->getTableName(),
                $column,
                $excludeWhere
            );

            $statement = $this->pdo->prepare($sql);
            $statement->execute(array_merge(['value' => $value], $excludeParams));
        } else {
            $sql = sprintf(
                'SELECT COUNT(*) FROM %s WHERE %s = :value',
                $this->getTableName(),
                $column
            );

            $statement = $this->pdo->prepare($sql);
            $statement->execute(['value' => $value]);
        }

        return (int) $statement->fetchColumn() > 0;
    }
}
