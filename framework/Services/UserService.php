<?php

declare(strict_types=1);

/**
 * Business logic service for user management.
 *
 * Coordinates higher-level user workflows and delegates persistence
 * CRUD operations to records.
 */

namespace Lampfire\Services;

use InvalidArgumentException;
use Lampfire\Config\SecuritySettings;
use Lampfire\Gateways\UserDataGateway;
use Lampfire\Gateways\UserGateway;
use Lampfire\Records\UserDataRecord;
use Lampfire\Records\UserRecord;
use Lampfire\Utilities\Enforcers;

class UserService extends AbstractService
{
    private UserGateway $userGateway;
    private UserDataGateway $userDataGateway;
    private UserRecord $userRecord;
    private UserDataRecord $userDataRecord;

    /**
     * Creates the user service.
     *
     * @param UserGateway $userGateway Gateway for user read and authorization queries.
     * @param UserDataGateway $userDataGateway Gateway for user profile lookup queries.
     * @param UserRecord $userRecord Record for Users table persistence.
     * @param UserDataRecord $userDataRecord Record for UserData table persistence.
     */
    public function __construct(
        UserGateway $userGateway,
        UserDataGateway $userDataGateway,
        UserRecord $userRecord,
        UserDataRecord $userDataRecord
    ) {
        $this->userGateway = $userGateway;
        $this->userDataGateway = $userDataGateway;
        $this->userRecord = $userRecord;
        $this->userDataRecord = $userDataRecord;
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
        Enforcers::enforceValidUuid($userId, 'user_id');

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
        Enforcers::enforceMinLength($username, SecuritySettings::MINIMUM_USERNAME_LENGTH, 'username');
        Enforcers::enforceMinLength($password, SecuritySettings::MINIMUM_PASSWORD_LENGTH, 'password');

        if ($this->userGateway->usernameExists($username)) {
            throw new InvalidArgumentException('The username is already taken.');
        }

        if ($this->userDataGateway->emailExists($emailAddress)) {
            throw new InvalidArgumentException('The email address is already registered.');
        }

        $userId = $this->generateUuid();
        $this->userGateway->insert($userId, $username, $this->hashPassword($password));

        $this->userDataRecord->create([
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email_address' => $emailAddress,
        ]);

        $created = $this->userGateway->findById($userId);
        if ($created === null) {
            throw new \RuntimeException('The user record could not be loaded after creation.');
        }

        return $created;
    }

    /**
     * Updates an existing user across both tables.
     *
     * Validates inputs, checks uniqueness excluding the current record,
     * and writes the changes to Users and UserData.
     *
     * @param string      $userId       The UUID of the user to update.
     * @param string      $emailAddress The new email address.
     * @param bool        $enabled      Whether the account is enabled.
     * @param string|null $firstName    The optional new first name.
     * @param string|null $lastName     The optional new last name.
     * @return array<string, mixed>|null The updated user record or null when not found.
     * @throws InvalidArgumentException When validation or uniqueness checks fail.
     */
    public function updateUser(
        string $userId,
        string $emailAddress,
        bool $enabled = true,
        ?string $firstName = null,
        ?string $lastName = null
    ): ?array {
        Enforcers::enforceValidUuid($userId, 'user_id');
        Enforcers::enforceValidEmail($emailAddress);

        $existingUser = $this->userGateway->findById($userId);
        if ($existingUser === null) {
            return null;
        }

        if ($this->userDataGateway->emailExists($emailAddress, $userId)) {
            throw new InvalidArgumentException('The email address is already registered.');
        }

        $this->userRecord->update([
            'user_id' => $userId,
            'enabled' => $enabled ? 1 : 0,
        ]);

        $existingUserData = $this->userDataRecord->getByPrimaryKey($userId);
        if ($existingUserData === null) {
            $this->userDataRecord->create([
                'user_id' => $userId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email_address' => $emailAddress,
            ]);
        } else {
            $this->userDataRecord->update([
                'user_id' => $userId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email_address' => $emailAddress,
            ]);
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
        return $this->setPassword($userId, $newPassword);
    }

    /**
     * Sets a user's password using framework business logic.
     *
     * @param string $userId      The UUID of the user.
     * @param string $newPassword The new plaintext password.
     * @return bool True when the password hash was updated.
     * @throws InvalidArgumentException When validation fails or the user does not exist.
     */
    public function setPassword(string $userId, string $newPassword): bool
    {
        Enforcers::enforceValidUuid($userId, 'user_id');
        Enforcers::enforceMinLength($newPassword, SecuritySettings::MINIMUM_PASSWORD_LENGTH, 'password');

        $existingUser = $this->userGateway->findById($userId);
        if ($existingUser === null) {
            throw new InvalidArgumentException('The specified user does not exist.');
        }

        $hash = $this->hashPassword($newPassword);

        return $this->userGateway->updatePasswordHash($userId, $hash);
    }

    /**
     * Validates a plaintext password for a given user.
     *
     * @param string $userId   The UUID of the user.
     * @param string $password The plaintext password candidate.
     * @return bool True when the password matches.
     * @throws InvalidArgumentException When the user identifier is invalid.
     */
    public function validatePassword(string $userId, string $password): bool
    {
        Enforcers::enforceValidUuid($userId, 'user_id');

        $user = $this->userGateway->findByIdWithHash($userId);
        if ($user === null) {
            return false;
        }

        $hash = $user['password_hash'] ?? null;
        if (is_string($hash) === false || $hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * Changes a user's password after validating the current password.
     *
     * @param string $userId          The UUID of the user.
     * @param string $currentPassword The current plaintext password.
     * @param string $newPassword     The new plaintext password.
     * @return bool True when the password was changed.
     * @throws InvalidArgumentException When validation fails.
     */
    public function changePassword(string $userId, string $currentPassword, string $newPassword): bool
    {
        Enforcers::enforceValidUuid($userId, 'user_id');

        if ($this->validatePassword($userId, $currentPassword) === false) {
            throw new InvalidArgumentException('The current password is incorrect.');
        }

        return $this->setPassword($userId, $newPassword);
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
     * @param string $username The username to test.
     * @return bool True when the username matches the configured superadmin.
     */
    public function isSuperadminUsername(string $username): bool
    {
        return $this->isSuperadmin($username);
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
