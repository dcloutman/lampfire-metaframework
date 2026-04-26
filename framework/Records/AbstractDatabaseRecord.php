<?php
declare(strict_types=1);

namespace Lampfire\Records;

use Lampfire\Database\DatabaseConnection;
use PDOStatement;

/**
 * AbstractDatabaseRecord serves as a base class for all database-backed record objects in the application.
 */
abstract class AbstractDatabaseRecord extends AbstractRecord
{
    /**
     * The last query made by the record.
     *
     * @var string|null
     */
    protected ?string $query = null;
    protected array $primaryKeys = [];
    protected array $fields = [];
    protected array $hiddenFields = [];
    protected array $readOnlyFields = [];
    protected DatabaseConnection $connection;
    /**
     * The last statement compiled by the record.
     *
     * @var PDOStatement|null
     */
    protected ?PDOStatement $statement = null;
    protected array $attributes = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $resultRows = [];
    protected int $cursor = -1;
    protected bool $resultSetExhausted = false;

    public function __construct(DatabaseConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns true if the given field name is valid.
     *
     * @param string $field
     * @return boolean
     */
    protected function isValidField(string $field): bool
    {
        return in_array($field, $this->fields, true);
    }

    /**
     * Builds a concrete record instance from a database row.
     *
     * @param array<string, mixed> $row The fetched row data.
     */
    protected function hydrate(array $row): self
    {
        $record = new static($this->connection);
        foreach ($row as $field => $value) {
            if ($this->isValidField($field)) {
                $record->setFieldValue($field, $value, true);
            }
        }

        return $record;
    }

    /**
     * Handles dynamic camelCase getters and setters for snake_case database fields.
     *
     * Examples: getUserId(), setUserId($value)
     *
     * @param string $name
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (str_starts_with($name, 'get')) {
            $field = $this->methodSuffixToFieldName(substr($name, 3));
            if (!$this->isValidField($field)) {
                throw new \BadMethodCallException('Unknown getter method ' . $name . ' for ' . static::class);
            }

            if ($this->isHiddenField($field)) {
                throw new \LogicException("Field {$field} is hidden for " . static::class);
            }

            return $this->getFieldValue($field);
        }

        if (str_starts_with($name, 'set')) {
            $field = $this->methodSuffixToFieldName(substr($name, 3));
            if (!$this->isValidField($field)) {
                throw new \BadMethodCallException("Unknown setter method {$name} for " . static::class);
            }

            if (count($arguments) !== 1) {
                throw new \InvalidArgumentException("Setter method {$name} requires exactly one argument.");
            }

            if ($this->isHiddenField($field)) {
                throw new \LogicException("Field {$field} is hidden for " . static::class);
            }

            if ($this->isReadOnlyField($field)) {
                throw new \LogicException("Field {$field} is read-only for " . static::class);
            }

            $this->setFieldValue($field, $arguments[0]);

            return $this;
        }

        throw new \BadMethodCallException("Unknown method {$name} for " . static::class);
    }

    /**
     * Sets the value of a field.
     *
     * @param string $field
     * @param mixed $value
     * @param boolean $allowProtectedMutation
     * @return void
     */
    protected function setFieldValue(string $field, mixed $value, bool $allowProtectedMutation = false): void
    {
        if (!$allowProtectedMutation && $this->isHiddenField($field)) {
            throw new \LogicException("Field {$field} is hidden for " . static::class);
        }

        if (!$allowProtectedMutation && $this->isReadOnlyField($field)) {
            throw new \LogicException("Field {$field} is read-only for " . static::class);
        }

        $setter = 'set' . ucfirst($this->fieldNameToMethodSuffix($field));

        if (method_exists($this, $setter)) {
            $this->{$setter}($value);

            return;
        }

        $this->attributes[$field] = $value;
    }

    protected function isHiddenField(string $field): bool
    {
        return in_array($field, $this->hiddenFields, true);
    }

    protected function isReadOnlyField(string $field): bool
    {
        return in_array($field, $this->readOnlyFields, true);
    }

    protected function getFieldValue(string $field): mixed
    {
        $getter = 'get' . ucfirst($this->fieldNameToMethodSuffix($field));

        if (method_exists($this, $getter)) {
            return $this->{$getter}();
        }

        if (!array_key_exists($field, $this->attributes)) {
            return null;
        }

        return $this->attributes[$field];
    }

    protected function fieldNameToMethodSuffix(string $fieldName): string
    {
        $parts = explode('_', $fieldName);
        if (count($parts) === 0) {
            return '';
        }

        $suffix = strtolower($parts[0]);
        $partCount = count($parts);
        for ($index = 1; $index < $partCount; $index++) {
            $suffix .= ucfirst(strtolower($parts[$index]));
        }

        return $suffix;
    }

    protected function methodSuffixToFieldName(string $methodSuffix): string
    {
        if ($methodSuffix === '') {
            return '';
        }

        $normalized = lcfirst($methodSuffix);
        $fieldName = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $normalized);
        if ($fieldName === null) {
            throw new \RuntimeException('Failed to map method suffix to field name for ' . static::class);
        }

        return strtolower($fieldName);
    }

    /**
     * Executes the provided query and resets navigation state.
     *
     * @param string $query The SQL statement to execute.
     * @param array<string, mixed> $queryParameters Named query parameters.
     */
    protected function executeQuery(string $query, array $queryParameters = []): void
    {
        if (trim($query) === '') {
            throw new \LogicException('Query not defined for ' . static::class);
        }

        // Track the last SQL statement this record attempted to execute.
        $this->query = $query;

        $this->statement = $this->connection->getConnection()->prepare($this->query);
        if ($this->statement === false) {
            throw new \RuntimeException('Failed to prepare statement for ' . static::class);
        }

        $result = $this->statement->execute($queryParameters);
        if ($result !== true) {
            throw new \RuntimeException('Failed to execute statement for ' . static::class);
        }

        $this->resultRows = [];
        $this->cursor = -1;
        $this->resultSetExhausted = false;
    }

    protected function ensureResultSetIsInitialized(): void
    {
        if ($this->statement === null) {
            throw new \LogicException(
                'No active statement exists for ' . static::class .
                '. Execute a query before calling paging methods.'
            );
        }
    }

    protected function hydrateCurrentCursorRow(): ?self
    {
        if ($this->cursor < 0 || $this->cursor >= count($this->resultRows)) {
            return null;
        }

        $row = $this->resultRows[$this->cursor];
        if (!is_array($row)) {
            throw new \RuntimeException('Result row has an invalid format for ' . static::class);
        }

        return $this->hydrate($row);
    }

    /**
     * Fetches one additional row from the active statement and appends it to the local cache.
     */
    protected function fetchNextRowIntoCache(): bool
    {
        $this->ensureResultSetIsInitialized();
        if ($this->resultSetExhausted) {
            return false;
        }

        $row = $this->statement->fetch();
        if ($row === false) {
            $this->resultSetExhausted = true;

            return false;
        }

        if (!is_array($row)) {
            throw new \RuntimeException('Result row has an invalid format for ' . static::class);
        }

        $this->resultRows[] = $row;

        return true;
    }

    /**
     * Retrieves a record by its primary key values.
     *
     * @param mixed ...$keyValues The values of the primary keys.
     * @return self|null The found record or null if not found.
     */
    public function getByPrimaryKey(...$keyValues): ?self
    {
        if (count($this->primaryKeys) === 0) {
            throw new \LogicException('Primary keys not defined for ' . static::class);
        }

        if (count($keyValues) !== count($this->primaryKeys)) {
            throw new \InvalidArgumentException('Incorrect number of primary key values provided.');
        }

        $queryParameters = [];
        foreach ($keyValues as $index => $value) {
            $primaryKey = $this->primaryKeys[$index];
            $queryParameters[$primaryKey] = $value;
        }

        $selectFields = count($this->fields) > 0 ? implode(', ', $this->fields) : '*';
        $whereClauses = implode(' AND ', array_map(fn($pk) => $pk . ' = :' . $pk, $this->primaryKeys));
        $query = 'SELECT ' . $selectFields . ' FROM ' . $this->resolveTableName() . ' WHERE ' . $whereClauses;

        $this->executeQuery($query, $queryParameters);
        if (!$this->fetchNextRowIntoCache()) {
            return null;
        }

        $this->cursor = 0;

        return $this->hydrateCurrentCursorRow();
    }

    public function previous(): ?self
    {
        $this->ensureResultSetIsInitialized();
        if ($this->cursor <= 0) {
            return null;
        }

        $this->cursor--;

        return $this->hydrateCurrentCursorRow();
    }

    public function next(): ?self
    {
        $this->ensureResultSetIsInitialized();
        if (($this->cursor + 1) >= count($this->resultRows)) {
            if (!$this->fetchNextRowIntoCache()) {
                return null;
            }
        }

        $this->cursor++;

        return $this->hydrateCurrentCursorRow();
    }

    public function rewind(): ?self
    {
        $this->ensureResultSetIsInitialized();
        if (count($this->resultRows) === 0 && !$this->fetchNextRowIntoCache()) {
            return null;
        }

        $this->cursor = 0;

        return $this->hydrateCurrentCursorRow();
    }

    public function count(): int
    {
        $this->ensureResultSetIsInitialized();

        while ($this->fetchNextRowIntoCache()) {
            // Count requires exhausting the active statement while avoiding eager fetchAll.
        }

        return count($this->resultRows);
    }

    /**
     * Creates a new record in the resolved database table.
     *
     * @param array<string, mixed> $data The field-value pairs to insert.
     * @return self The created record when it can be reloaded, otherwise a hydrated local record.
     */
    public function create(array $data): self
    {
        if (count($data) === 0) {
            throw new \InvalidArgumentException('Create requires at least one field for ' . static::class);
        }

        $insertFields = [];
        $parameters = [];

        foreach ($data as $field => $value) {
            if (!is_string($field) || $field === '') {
                throw new \InvalidArgumentException('Create received an invalid field name for ' . static::class);
            }

            if (!$this->isValidField($field)) {
                throw new \InvalidArgumentException('Unknown field ' . $field . ' for ' . static::class);
            }

            if ($this->isHiddenField($field)) {
                throw new \LogicException('Field ' . $field . ' is hidden for ' . static::class);
            }

            if ($this->isReadOnlyField($field)) {
                throw new \LogicException('Field ' . $field . ' is read-only for ' . static::class);
            }

            $insertFields[] = $field;
            $parameters[$field] = $value;
        }

        $columns = implode(', ', $insertFields);
        $placeholders = ':' . implode(', :', $insertFields);

        $query = 'INSERT INTO ' . $this->resolveTableName() . ' (' . $columns . ') VALUES (' . $placeholders . ')';
        $this->query = $query;

        $statement = $this->connection->getConnection()->prepare($query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare insert statement for ' . static::class);
        }

        $success = $statement->execute($parameters);
        if ($success !== true) {
            throw new \RuntimeException('Failed to execute insert statement for ' . static::class);
        }

        if (count($this->primaryKeys) === 0) {
            return $this->hydrate($parameters);
        }

        $primaryValues = [];
        foreach ($this->primaryKeys as $primaryKey) {
            if (array_key_exists($primaryKey, $parameters)) {
                $primaryValues[] = $parameters[$primaryKey];
                continue;
            }

            if (count($this->primaryKeys) === 1) {
                $lastInsertId = $this->connection->getConnection()->lastInsertId();
                if ($lastInsertId !== false && $lastInsertId !== '') {
                    $primaryValues[] = $lastInsertId;
                    continue;
                }
            }

            return $this->hydrate($parameters);
        }

        $created = $this->getByPrimaryKey(...$primaryValues);
        if ($created === null) {
            return $this->hydrate($parameters);
        }

        return $created;
    }

    public function update(array $data): self
    {
        if (count($data) === 0) {
            throw new \InvalidArgumentException('Update requires at least one field for ' . static::class);
        }

        $updateFields = [];
        $parameters = [];

        foreach ($data as $field => $value) {
            if (!is_string($field) || $field === '') {
                throw new \InvalidArgumentException('Update received an invalid field name for ' . static::class);
            }

            if (!$this->isValidField($field)) {
                throw new \InvalidArgumentException('Unknown field ' . $field . ' for ' . static::class);
            }

            if ($this->isHiddenField($field)) {
                throw new \LogicException('Field ' . $field . ' is hidden for ' . static::class);
            }

            if ($this->isReadOnlyField($field)) {
                throw new \LogicException('Field ' . $field . ' is read-only for ' . static::class);
            }

            $parameters[$field] = $value;

            if (in_array($field, $this->primaryKeys, true)) {
                continue;
            }

            $updateFields[] = $field;
        }

        if (count($updateFields) === 0) {
            throw new \InvalidArgumentException('No valid fields provided for update for ' . static::class);
        }

        if (count($this->primaryKeys) === 0) {
            throw new \LogicException('Primary keys not defined for update for ' . static::class);
        }

        foreach ($this->primaryKeys as $primaryKey) {
            if (!array_key_exists($primaryKey, $parameters)) {
                throw new \InvalidArgumentException('Missing primary key value for update for ' . static::class);
            }
        }

        $setClauses = implode(', ', array_map(fn($f) => $f . ' = :set_' . $f, $updateFields));
        $whereClauses = implode(' AND ', array_map(fn($pk) => $pk . ' = :where_' . $pk, $this->primaryKeys));

        $query = 'UPDATE ' . $this->resolveTableName() . ' SET ' . $setClauses . ' WHERE ' . $whereClauses;
        $this->query = $query;

        $statement = $this->connection->getConnection()->prepare($query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare update statement for ' . static::class);
        }

        $statementParameters = [];

        foreach ($updateFields as $field) {
            $statementParameters['set_' . $field] = $parameters[$field];
        }

        foreach ($this->primaryKeys as $primaryKey) {
            $statementParameters['where_' . $primaryKey] = $parameters[$primaryKey];
        }

        $success = $statement->execute($statementParameters);
        if ($success !== true) {
            throw new \RuntimeException('Failed to execute update statement for ' . static::class);
        }

        return $this;
    }

    /**
     * Deletes the current record from the database.
     */
    public function delete(): bool
    {
        if (count($this->primaryKeys) === 0) {
            throw new \LogicException('Primary keys not defined for delete for ' . static::class);
        }

        $parameters = [];
        foreach ($this->primaryKeys as $primaryKey) {
            $value = $this->getFieldValue($primaryKey);
            if ($value === null) {
                throw new \LogicException('Primary key value is null for delete for ' . static::class);
            }

            $parameters[$primaryKey] = $value;
        }

        $whereClauses = implode(' AND ', array_map(fn($pk) => $pk . ' = :' . $pk, $this->primaryKeys));

        $query = 'DELETE FROM ' . $this->resolveTableName() . ' WHERE ' . $whereClauses;
        $this->query = $query;

        $statement = $this->connection->getConnection()->prepare($query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare delete statement for ' . static::class);
        }

        $success = $statement->execute($parameters);
        if ($success !== true) {
            throw new \RuntimeException('Failed to execute delete statement for ' . static::class);
        }

        return $statement->rowCount() > 0;
    }

    /**
     * Resolves the table name by convention from the record class name.
     */
    protected function resolveTableName(): string
    {
        $shortName = (new \ReflectionClass($this))->getShortName();
        if (!str_ends_with($shortName, 'Record')) {
            throw new \LogicException('Unable to resolve table name for ' . static::class);
        }

        $entityName = substr($shortName, 0, -strlen('Record'));
        if ($entityName === false || $entityName === '') {
            throw new \LogicException('Unable to resolve table name for ' . static::class);
        }

        return $entityName . 's';
    }
}
