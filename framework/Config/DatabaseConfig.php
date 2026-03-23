<?php

declare(strict_types=1);

/**
 * Database configuration read from the environment.
 *
 * Validates and exposes connection parameters. This class owns the
 * environment variables; application code should never call getenv()
 * for database values. Inject this object wherever connection
 * details are needed.
 *
 * The actual PDO connection is managed by DatabaseConnection.
 */

namespace Lampfire\Config;

class DatabaseConfig extends AbstractSettings
{
    private string $host;
    private int $port;
    private string $name;
    private string $user;
    private string $password;

    /**
     * Reads and validates database configuration from the environment.
     *
     * @throws \RuntimeException When a required variable is missing.
     */
    public function __construct()
    {
        $this->host     = $this->getRequiredEnvironmentString('DATABASE_HOST');
        $this->port     = $this->getOptionalEnvironmentInt('DATABASE_PORT', 3306);
        $this->name     = $this->getRequiredEnvironmentString('DATABASE_NAME');
        $this->user     = $this->getRequiredEnvironmentString('DATABASE_APPLICATION_USER');
        $this->password = $this->getRequiredEnvironmentString('DATABASE_APPLICATION_USER_PASSWORD');
    }

    /**
     * Returns the database hostname.
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Returns the database port.
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Returns the database name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the database user.
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * Returns the database password.
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Builds and returns the PDO DSN string.
     */
    public function getDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->port,
            $this->name
        );
    }
}
