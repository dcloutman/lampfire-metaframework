<?php

declare(strict_types=1);

/**
 * Data gateway for the Users table.
 *
 * Handles CRUD operations and queries against the authentication-facing
 * Users table. Personal contact information lives in the separate UserData
 * table and is accessible through joined queries.
 */

namespace Lampfire\Gateways;

use Lampfire\Utilities\Enforcers;


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
        $sql = 'SELECT u.user_id, u.username, u.enabled,
                       ud.first_name, ud.last_name, ud.email_address,
                       u.created_at, u.updated_at
                FROM Users u
                LEFT JOIN UserData ud ON ud.user_id = u.user_id
                ORDER BY u.created_at DESC';

        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            throw new \RuntimeException('Failed to execute SQL query.');
        }

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
        Enforcers::enforceValidUuid($userId, 'user_id');

        $sql = 'SELECT u.user_id, u.username, u.enabled,
                       ud.first_name, ud.last_name, ud.email_address,
                       u.created_at, u.updated_at
                FROM Users u
                LEFT JOIN UserData ud ON ud.user_id = u.user_id
                WHERE u.user_id = :user_id';

        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute(['user_id' => $userId]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

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
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute(['username' => $username]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        $row = $statement->fetch();

        return ($row === false) ? null : $row;
    }

    /**
     * Returns a user by identifier including the password hash.
     *
     * This method is intended for internal authentication workflows.
     *
     * @param string $userId The UUID primary key.
     * @return array<string, mixed>|null The user row or null when not found.
     * @throws \InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function findByIdWithHash(string $userId): ?array
    {
        Enforcers::enforceValidUuid($userId, 'user_id');

        $sql = 'SELECT user_id, username, password_hash,
                       created_at, updated_at
                FROM Users
                WHERE user_id = :user_id';

        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute(['user_id' => $userId]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

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
        Enforcers::enforceValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'INSERT INTO Users (user_id, username, password_hash)
             VALUES (:user_id, :username, :password_hash)'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'user_id'       => $userId,
            'username'      => $username,
            'password_hash' => $passwordHash,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return true;
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
        Enforcers::enforceValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'UPDATE Users
             SET password_hash = :password_hash
             WHERE user_id = :user_id'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'user_id'       => $userId,
            'password_hash' => $passwordHash,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->rowCount() > 0;
    }

    /**
     * Sets the enabled state for a user account.
     *
     * @param string $userId  The UUID of the user.
     * @param bool   $enabled True to enable the account, false to disable it.
     * @return bool True when the update affects a row.
     * @throws \InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function setEnabled(string $userId, bool $enabled): bool
    {
        Enforcers::enforceValidUuid($userId, 'user_id');

        $statement = $this->pdo->prepare(
            'UPDATE Users SET enabled = :enabled WHERE user_id = :user_id'
        );
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'user_id' => $userId,
            'enabled' => $enabled ? 1 : 0,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

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
     * Optionally excludes a single row by primary key so that update
     * uniqueness checks do not flag the record being edited.
     *
     * @param string      $username      The username to check.
     * @param string|null $excludeUserId An optional user_id to exclude from the check.
     * @return bool True when the username is already taken.
     * @throws \RuntimeException When the SQL statement fails to prepare or execute.
     */
    public function usernameExists(string $username, ?string $excludeUserId = null): bool
    {
        $query = 'SELECT COUNT(*) FROM Users WHERE username = :username';
        $params = ['username' => $username];

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

    /**
     * Returns true when the user holds the specified permission token.
     *
     * Both grant paths are evaluated: permissions inherited through a
     * UserGroup membership and permissions assigned directly via a
     * PermissionSetMembers entry. A membership is considered active only
     * when its has_access flag is set and its access_expiry has not passed.
     *
     * @param string $userId          The UUID of the user to check.
     * @param string $permissionToken The programmatic permission token.
     * @return bool True when the user has the permission through either path.
     * @throws \RuntimeException When the SQL statement fails.
     */
    public function userHasPermissionToken(string $userId, string $permissionToken): bool
    {
        Enforcers::enforceValidUuid($userId, 'user_id');

        $sql = 'SELECT 1
                WHERE EXISTS (
                    SELECT 1
                    FROM UserGroupMemberships ugm
                    INNER JOIN UserGroups ug
                        ON ug.user_group_id = ugm.user_group_id
                       AND ug.enabled = 1
                    INNER JOIN PermissionSetUserGroups psug
                        ON psug.user_group_id = ug.user_group_id
                       AND psug.has_access = 1
                       AND psug.access_expiry > UTC_TIMESTAMP()
                    INNER JOIN PermissionSetPermissions psp
                        ON psp.permission_set_id = psug.permission_set_id
                    INNER JOIN Permissions p
                        ON p.permission_id = psp.permission_id
                       AND p.permission_token = :token_group
                    WHERE ugm.user_id = :uid_group
                      AND ugm.has_access = 1
                      AND ugm.access_expiry > UTC_TIMESTAMP()
                )
                OR EXISTS (
                    SELECT 1
                    FROM PermissionSetMembers psm
                    INNER JOIN PermissionSetPermissions psp
                        ON psp.permission_set_id = psm.permission_set_id
                    INNER JOIN Permissions p
                        ON p.permission_id = psp.permission_id
                       AND p.permission_token = :token_direct
                    WHERE psm.user_id = :uid_direct
                      AND psm.has_access = 1
                      AND psm.access_expiry > UTC_TIMESTAMP()
                )';

        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement.');
        }

        $success = $statement->execute([
            'uid_group'    => $userId,
            'token_group'  => $permissionToken,
            'uid_direct'   => $userId,
            'token_direct' => $permissionToken,
        ]);
        if ($success === false) {
            throw new \RuntimeException('Failed to execute SQL statement.');
        }

        return $statement->fetch() !== false;
    }
}
