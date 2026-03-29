<?php

declare(strict_types=1);

/**
 * Data gateway for the UserData table.
 *
 * The UserData table stores personally identifiable information (PII)
 * such as email addresses, separated from the authentication credentials
 * stored in the Users table.
 */

namespace Lampfire\Gateways;

use Lampfire\Utilities\Enforcers;


class UserDataGateway extends AbstractDatabaseGateway
{
    /**
     * {@inheritDoc}
     */
    protected function getTableName(): string
    {
        return 'UserData';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPrimaryKeyColumns(): array
    {
        return ['user_id'];
    }

    /**
     * Returns a user data record by user identifier.
     *
     * @param string $userId The UUID foreign key.
     * @return array<string, mixed>|null The user data row or null when not found.
     * @throws \InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function findByUserId(string $userId): ?array
    {
        return $this->fetchByPrimaryKey(
            ['user_id' => $userId],
            'user_id, first_name, last_name, email_address, created_at, updated_at'
        );
    }

    /**
     * Returns a user data record by email address.
     *
     * @param string $emailAddress The email address to look up.
     * @return array<string, mixed>|null The user data row or null when not found.
     */
    public function findByEmail(string $emailAddress): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT user_id, first_name, last_name, email_address, created_at, updated_at
             FROM UserData
             WHERE email_address = :email_address'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute(['email_address' => $emailAddress]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        $row = $statement->fetch();

        return ($row === false) ? null : $row;
    }

    /**
     * Inserts a new user data record.
     *
     * @param string      $userId       The UUID of the associated user.
     * @param string      $emailAddress The email address.
     * @param string|null $firstName    The optional first name.
     * @param string|null $lastName     The optional last name.
     * @return bool True when the insert succeeds.
     * @throws \InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function insert(
        string $userId,
        string $emailAddress,
        ?string $firstName = null,
        ?string $lastName = null
    ): bool {
        Enforcers::enforceValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'INSERT INTO UserData (user_id, first_name, last_name, email_address)
             VALUES (:user_id, :first_name, :last_name, :email_address)'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'user_id'       => $userId,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'email_address' => $emailAddress,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return true;
    }

    /**
     * Updates a user data record.
     *
     * @param string      $userId       The UUID of the user.
     * @param string      $emailAddress The new email address.
     * @param string|null $firstName    The optional new first name.
     * @param string|null $lastName     The optional new last name.
     * @return bool True when the update affects at least one row.
     * @throws \InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function update(
        string $userId,
        string $emailAddress,
        ?string $firstName = null,
        ?string $lastName = null
    ): bool {
        Enforcers::enforceValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'UPDATE UserData
             SET email_address = :email_address,
                 first_name = :first_name,
                 last_name = :last_name
             WHERE user_id = :user_id'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'user_id'       => $userId,
            'email_address' => $emailAddress,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->rowCount() > 0;
    }

    /**
     * Deletes a user data record by user identifier.
     *
     * @param string $userId The UUID of the user.
     * @return bool True when a row was deleted.
     * @throws \InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function deleteByUserId(string $userId): bool
    {
        return $this->deleteByPrimaryKey(['user_id' => $userId]);
    }

    /**
     * Checks whether an email address already exists in the UserData table.
     *
     * Optionally excludes a single row by primary key so that update
     * uniqueness checks do not flag the record being edited.
     *
     * @param string      $emailAddress  The email to check.
     * @param string|null $excludeUserId An optional user_id to exclude.
     * @return bool True when the email is already registered.
     * @throws \RuntimeException When the SQL statement fails to prepare or execute.
     */
    public function emailExists(string $emailAddress, ?string $excludeUserId = null): bool
    {
        $query = 'SELECT COUNT(*) FROM UserData WHERE email_address = :email_address';
        $params = ['email_address' => $emailAddress];

        if (is_string($excludeUserId)) {
            $query .= ' AND user_id != :user_id';
            $params['user_id'] = $excludeUserId;
        }

        $statement = $this->pdo->prepare($query);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute($params);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return (int) $statement->fetchColumn() > 0;
    }
}
