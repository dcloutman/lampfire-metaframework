<?php

declare(strict_types=1);

/**
 * Manages the PDO database connection.
 *
 * Accepts a Database configuration object and creates a PDO instance
 * with safe defaults. The connection is created lazily on first call
 * to getConnection() and reused for subsequent calls.
 */

namespace Lampfire\Database;

use Lampfire\Config\DatabaseConfig;
use PDO;
use PDOException;

class DatabaseConnection
{
    private DatabaseConfig $config;
    private ?PDO $pdoInstance = null;

    /**
     * Creates the connection manager.
     *
     * @param DatabaseConfig $config Database configuration read from the environment.
     */
    public function __construct(DatabaseConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Returns the PDO connection, creating it on first access.
     *
     * @return PDO A configured database connection.
     * @throws PDOException When the connection cannot be established.
     */
    public function getConnection(): PDO
    {
        if ($this->pdoInstance === null) {
            $this->pdoInstance = new PDO(
                $this->config->getDsn(),
                $this->config->getUser(),
                $this->config->getPassword(),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }

        return $this->pdoInstance;
    }

    /**
     * Returns the underlying database configuration.
     */
    public function getConfig(): DatabaseConfig
    {
        return $this->config;
    }

    /**
     * Resets the PDO connection by discarding the current instance and creating a new one.
     * 
     * Use with caution. Calling this function may invalidate any existing references to the PDO instance and terminate any ongoing transactions.
     */
    public function resetConnection(): PDO
    {
        $this->pdoInstance = null;
        return $this->getConnection();
    }
}
