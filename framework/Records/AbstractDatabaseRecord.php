<?php
declare(strict_types=1);

namespace Lampfire\Records;

use PDOStatement;

/**
 * AbstractDatabaseRecord serves as a base class for all database-backed record objects in the application.
 */
abstract class AbstractDatabaseRecord extends AbstractRecord {
    protected static string|null $query = null;
    protected static PDOStatement|null $statement = null;
    protected static array $primaryKeys = [];

    public static function getByPrimaryKey(...$keyValues): ?self
    {
        if (static::$query === null) {
            throw new \LogicException('Query not defined for ' . static::class);
        }
        if (count($keyValues) !== count(static::$primaryKeys)) {
            throw new \InvalidArgumentException('Incorrect number of primary key values provided.');
        }

        if (static::$statement === null) {
            static::$statement = Database::getConnection()->prepare(static::$query);
            if (static::$statement === false) {
                throw new \RuntimeException('Failed to prepare statement for ' . static::class);
            }
        }

        foreach ($keyValues as $index => $value) {
            static::$statement->bindValue($index + 1, $value);
        }

        $result = static::$statement->execute();
        if ($result === false) {
            throw new \RuntimeException('Failed to execute statement for ' . static::class);
        }
        
        $data = static::$statement->fetch();

        if ($data === false) {
            return null;
        }

        return new static($data);
    }
}
