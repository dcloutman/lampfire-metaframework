<?php

declare(strict_types=1);

/**
 * Data gateway for the Users table.
 *
 * Handles CRUD operations and queries against the authentication-facing
 * Users table. Personal contact information lives in the separate UserData
 * table and is accessible through joined queries.
 */

namespace App\Gateways;

use Lampfire\Gateways\AbstractDatabaseGateway;

class UserGateway extends AbstractDatabaseGateway
{
    /**
     * {@inheritDoc}
     */
    protected function getTableName(): string
    {
        return 'Users';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPrimaryKeyColumns(): array
    {
        return ['user_id'];
    }

    /**
     * Returns all users with PII joined from UserData, ordered by
     * creation date descending.
     *
     * The password hash is never included in list results.
     *
     * @return array<int, array<string, mixed>> A list of user rows.
     */
    public function findAll(): array
    {
        $sql = 'SELECT u.user_id, u.username,
                       ud.first_name, ud.last_name, ud.email_address,
                       u.created_at, u.updated_at
                FROM Users u
                LEFT JOIN UserData ud ON ud.user_id = u.user_id
                ORDER BY u.created_at DESC';

        $statement = $this->pdo->query($sql);

        return $statement->fetchAll();
    }

    /**
     * Returns a single user with PII joined, without the password hash.
     *
     * @param string $userId The UUID primary key.
     * @return array<string, mixed>|null The user row or null when not found.
     * @throws \InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function findById(string $userId): ?array
    {
        $this->requireValidUuid($userId, 'user_id');

        $sql = 'SELECT u.user_id, u.username,
                       ud.first_name, ud.last_name, ud.email_address,
                       u.created_at, u.updated_at
                FROM Users u
                LEFT JOIN UserData ud ON ud.user_id = u.user_id
                WHERE u.user_id = :user_id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['user_id' => $userId]);

        $row = $statement->fetch();

        return ($row === false) ? null : $row;
    }

    /**
     * Returns a user by username including the password hash.
     *
     * This method is intended for authentication only. Do not expose
     * the password hash through any external interface.
     *
     * @param string $username The login username.
     * @return array<string, mixed>|null The user row or null when not found.
     */
    public function findByUsernameWithHash(string $username): ?array
    {
        $sql = 'SELECT user_id, username, password_hash,
                       created_at, updated_at
                FROM Users
                WHERE username = :username';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['username' => $username]);

        $row = $statement->fetch();

        return ($row === false) ? null : $row;
    }

    /**
     * Inserts a new user record.
     *
     * @param string $userId       The pre-generated UUID.
     * @param string $username     The unique login name.
     * @param string $passwordHash The Argon2id password hash.
     * @return bool True when the insert succeeds.
     * @throws \InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function insert(
        string $userId,
        string $username,
        string $passwordHash
    ): bool {
        $this->requireValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'INSERT INTO Users (user_id, username, password_hash)
             VALUES (:user_id, :username, :password_hash)'
        );

        return $statement->execute([
            'user_id'       => $userId,
            'username'      => $username,
            'password_hash' => $passwordHash,
        ]);
    }

    /**
     * Updates the username for an existing user record.
     *
     * @param string $userId   The UUID of the user.
     * @param string $username The new username.
     * @return bool True when the update affects at least one row.
     * @throws \InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function update(
        string $userId,
        string $username
    ): bool {
        $this->requireValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'UPDATE Users
             SET username = :username
             WHERE user_id = :user_id'
        );

        $statement->execute([
            'user_id'  => $userId,
            'username' => $username,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Updates the password hash for a specific user.
     *
     * @param string $userId       The UUID of the user.
     * @param string $passwordHash The new Argon2id password hash.
     * @return bool True when the update succeeds.
     * @throws \InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function updatePasswordHash(string $userId, string $passwordHash): bool
    {
        $this->requireValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'UPDATE Users
             SET password_hash = :password_hash
             WHERE user_id = :user_id'
        );

        $statement->execute([
            'user_id'       => $userId,
            'password_hash' => $passwordHash,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Deletes a user record by primary key.
     *
     * @param string $userId The UUID of the user.
     * @return bool True when a row was deleted.
     * @throws \InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function deleteById(string $userId): bool
    {
        return $this->deleteByPrimaryKey(['user_id' => $userId]);
    }

    /**
     * Checks whether a username already exists in the Users table.
     *
     * @param string      $username      The username to check.
     * @param string|null $excludeUserId An optional user_id to exclude from the check.
     * @return bool True when the username is already taken.
     */
    public function usernameExists(string $username, ?string $excludeUserId = null): bool
    {
        $excludeKey = is_string($excludeUserId) ? ['user_id' => $excludeUserId] : null;

        return $this->valueExists('username', $username, $excludeKey);
    }
}
