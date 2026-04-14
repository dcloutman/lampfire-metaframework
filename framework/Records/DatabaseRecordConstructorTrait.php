<?php

namespace Lampfire\Records;

use Lampfire\Database\DatabaseConnection;

trait DatabaseRecordConstructorTrait
{
    /**
     * A defacto constructor implementation for database records.
     *
     * @param DatabaseConnection $connection
     * @param array $fields
     */
    public function __construct(DatabaseConnection $connection)
    {
        $this->connection = $connection;
    }
}
