<?php
declare(strict_types=1);

namespace Lampfire\Records;

use Lampfire\Database\DatabaseConnection;

/**
 * AbstractDatabaseRecord serves as a base class for all database-backed record objects in the application.
 */
abstract class AbstractDatabaseRecord extends AbstractRecord
{
    protected ?string $query = null;
    protected array $primaryKeys = [];
    protected DatabaseConnection $connection;

    public function __construct(DatabaseConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Builds a concrete record instance from a database row.
     *
     * @param array<string, mixed> $row The fetched row data.
     */
    abstract protected function hydrate(array $row): self;

    public function getByPrimaryKey(...$keyValues): ?self
    {
        if ($this->query === null || trim($this->query) === '') {
            throw new \LogicException('Query not defined for ' . static::class);
        }

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

        $statement = $this->connection->getConnection()->prepare($this->query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare statement for ' . static::class);
        }

        $result = $statement->execute($queryParameters);
        if ($result !== true) {
            throw new \RuntimeException('Failed to execute statement for ' . static::class);
        }

        $data = $statement->fetch();

        if ($data === false) {
            return null;
        }

        return $this->hydrate($data);
    }
}
