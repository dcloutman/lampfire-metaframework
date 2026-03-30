<?php

declare(strict_types=1);

/**
 * Business logic service for user management.
 *
 * Coordinates between the Users table (credentials) and the UserData
 * table (PII) to provide a unified interface for user CRUD operations.
 * All read operations use the UserGateway's SQL JOINs to avoid N+1
 * queries. Password hashing uses Argon2id via the inherited helper.
 */

namespace Lampfire\Services;

use Lampfire\Config\SecuritySettings;
use Lampfire\Gateways\UserDataGateway;
use Lampfire\Gateways\UserGateway;
use InvalidArgumentException;

class UserService extends AbstractService
{
    /**
     * @var UserGateway Gateway for the Users table.
     */
    private UserGateway $userGateway;

    /**
     * @var UserDataGateway Gateway for the UserData table.
     */
    private UserDataGateway $userDataGateway;

    /**
     * Creates the user service.
     *
     * @param UserGateway     $userGateway     Gateway for the Users table.
     * @param UserDataGateway $userDataGateway Gateway for the UserData table.
     */
    public function __construct(UserGateway $userGateway, UserDataGateway $userDataGateway)
    {
        $this->userGateway = $userGateway;
        $this->userDataGateway = $userDataGateway;
    }

    /**
     * Returns all users with their PII data joined from UserData.
     *
     * @return array<int, array<string, mixed>> A list of user records.
     */
    public function getAllUsers(): array
    {
        return $this->userGateway->findAll();
    }

    /**
     * Returns a single user with joined PII data.
     *
     * @param string $userId The UUID primary key.
     * @return array<string, mixed>|null The user record or null.
     * @throws InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function getUserById(string $userId): ?array
    {
        $this->requireValidUuid($userId, 'user_id');

        return $this->userGateway->findById($userId);
    }

    /**
     * Creates a new user across both tables inside a logical unit.
     *
     * Validates all inputs, checks for uniqueness of username and email,
     * generates a UUID v4, hashes the password with Argon2id, and inserts
     * rows into Users and UserData.
     *
     * @param string      $username     The unique login name (minimum 6 characters).
     * @param string      $password     The plaintext password (minimum 12 characters).
     * @param string      $emailAddress The email address for the UserData record.
     * @param string|null $firstName    The optional first name.
     * @param string|null $lastName     The optional last name.
     * @return array<string, mixed> The newly created user record with joined PII.
     * @throws InvalidArgumentException When validation or uniqueness checks fail.
     */
    public function createUser(
        string $username,
        string $password,
        string $emailAddress,
        ?string $firstName = null,
        ?string $lastName = null
    ): array {
        $this->requireMinLength($username, SecuritySettings::MINIMUM_USERNAME_LENGTH, 'username');
        $this->requireMinLength($password, SecuritySettings::MINIMUM_PASSWORD_LENGTH, 'password');

        if ($this->userGateway->usernameExists($username)) {
            throw new InvalidArgumentException('The username is already taken.');
        }

        if ($this->userDataGateway->emailExists($emailAddress)) {
            throw new InvalidArgumentException('The email address is already registered.');
        }

        $userId       = $this->generateUuid();
        $passwordHash = $this->hashPassword($password);

        $this->userGateway->insert($userId, $username, $passwordHash);
        $this->userDataGateway->insert($userId, $emailAddress, $firstName, $lastName);

        return $this->userGateway->findById($userId);
    }

    /**
     * Updates an existing user across both tables.
     *
     * Validates inputs, checks uniqueness excluding the current record,
     * and writes the changes to Users and UserData.
     *
     * @param string      $userId       The UUID of the user to update.
     * @param string      $emailAddress The new email address.
     * @param string|null $firstName    The optional new first name.
     * @param string|null $lastName     The optional new last name.
     * @return array<string, mixed>|null The updated user record or null when not found.
     * @throws InvalidArgumentException When validation or uniqueness checks fail.
     */
    public function updateUser(
        string $userId,
        string $emailAddress,
        ?string $firstName = null,
        ?string $lastName = null
    ): ?array {
        $this->requireValidUuid($userId, 'user_id');
        $this->requireValidEmail($emailAddress);

        $existingUser = $this->userGateway->findById($userId);
        if ($existingUser === null) {
            return null;
        }

        if ($this->userDataGateway->emailExists($emailAddress, $userId)) {
            throw new InvalidArgumentException('The email address is already registered.');
        }

        // Update or create the UserData record for PII.
        $existingData = $this->userDataGateway->findByUserId($userId);
        if ($existingData !== null) {
            $this->userDataGateway->update($userId, $emailAddress, $firstName, $lastName);
        } else {
            $this->userDataGateway->insert($userId, $emailAddress, $firstName, $lastName);
        }

        return $this->userGateway->findById($userId);
    }

    /**
     * Resets a user's password.
     *
     * Validates the new password length and verifies the user exists
     * before updating the hash.
     *
     * @param string $userId      The UUID of the user.
     * @param string $newPassword The new plaintext password (minimum 12 characters).
     * @return bool True when the password was successfully updated.
     * @throws InvalidArgumentException When the user_id or password is invalid.
     */
    public function resetPassword(string $userId, string $newPassword): bool
    {
        $this->requireValidUuid($userId, 'user_id');
        $this->requireMinLength($newPassword, SecuritySettings::MINIMUM_PASSWORD_LENGTH, 'password');

        $existingUser = $this->userGateway->findById($userId);
        if ($existingUser === null) {
            throw new InvalidArgumentException('The specified user does not exist.');
        }

        $hash = $this->hashPassword($newPassword);

        return $this->userGateway->updatePasswordHash($userId, $hash);
    }

    /**
     * Returns true when the given user is authorized to write User records.
     *
     * Authorization is granted when the user is the active superadmin (identified
     * by their username matching the SUPERADMIN_USERNAME environment variable) or
     * when they hold the specified admin permission token through a group membership
     * or a direct permission set assignment.
     *
     * @param string $authUserId        The UUID of the authenticated user.
     * @param string $authUsername      The username of the authenticated user.
     * @param string $permissionToken   The permission token required for the operation.
     * @return bool True when the user is authorized.
     */
    public function isAuthorizedForUserWrite(
        string $authUserId,
        string $authUsername,
        string $permissionToken
    ): bool {
        if ($this->isSuperadmin($authUsername)) {
            return true;
        }

        return $this->userGateway->userHasPermissionToken($authUserId, $permissionToken);
    }

    /**
     * Returns true when the given username matches the active superadmin username.
     *
     * The superadmin is transient. When the SUPERADMIN_USERNAME environment variable
     * is absent or empty, no user is considered a superadmin.
     *
     * @param string $username The username to test.
     * @return bool True when the username matches the configured superadmin.
     */
    private function isSuperadmin(string $username): bool
    {
        $superadmin = getenv('SUPERADMIN_USERNAME');

        return $superadmin !== false && $superadmin !== '' && $username === $superadmin;
    }
}
