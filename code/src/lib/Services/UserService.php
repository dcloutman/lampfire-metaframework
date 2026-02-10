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

namespace App\Services;

use App\Gateways\UserDataGateway;
use App\Gateways\UserGateway;
use InvalidArgumentException;
use Lampfire\Services\AbstractService;

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
     * @param string      $username     The unique login name (minimum 3 characters).
     * @param string      $password     The plaintext password (minimum 8 characters).
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
        $this->requireMinLength($username, 3, 'username');
        $this->requireMinLength($password, 8, 'password');
        $this->requireValidEmail($emailAddress);

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
     * @param string      $username     The new username.
     * @param string      $emailAddress The new email address.
     * @param string|null $firstName    The optional new first name.
     * @param string|null $lastName     The optional new last name.
     * @return array<string, mixed>|null The updated user record or null when not found.
     * @throws InvalidArgumentException When validation or uniqueness checks fail.
     */
    public function updateUser(
        string $userId,
        string $username,
        string $emailAddress,
        ?string $firstName = null,
        ?string $lastName = null
    ): ?array {
        $this->requireValidUuid($userId, 'user_id');
        $this->requireMinLength($username, 3, 'username');
        $this->requireValidEmail($emailAddress);

        $existingUser = $this->userGateway->findById($userId);
        if ($existingUser === null) {
            return null;
        }

        if ($this->userGateway->usernameExists($username, $userId)) {
            throw new InvalidArgumentException('The username is already taken.');
        }

        if ($this->userDataGateway->emailExists($emailAddress, $userId)) {
            throw new InvalidArgumentException('The email address is already registered.');
        }

        $this->userGateway->update($userId, $username);

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
     * @param string $newPassword The new plaintext password (minimum 8 characters).
     * @return bool True when the password was successfully updated.
     * @throws InvalidArgumentException When the user_id or password is invalid.
     */
    public function resetPassword(string $userId, string $newPassword): bool
    {
        $this->requireValidUuid($userId, 'user_id');
        $this->requireMinLength($newPassword, 8, 'password');

        $existingUser = $this->userGateway->findById($userId);
        if ($existingUser === null) {
            throw new InvalidArgumentException('The specified user does not exist.');
        }

        $hash = $this->hashPassword($newPassword);

        return $this->userGateway->updatePasswordHash($userId, $hash);
    }

    /**
     * Deletes a user and the associated PII record.
     *
     * The UserData record is deleted first to respect the foreign key
     * constraint. Returns false when the user does not exist.
     *
     * @param string $userId The UUID of the user.
     * @return bool True when the user was deleted.
     * @throws InvalidArgumentException When the user_id is not a valid UUID.
     */
    public function deleteUser(string $userId): bool
    {
        $this->requireValidUuid($userId, 'user_id');

        $existingUser = $this->userGateway->findById($userId);
        if ($existingUser === null) {
            return false;
        }

        // Delete the PII record before the credential record.
        $this->userDataGateway->deleteByUserId($userId);

        return $this->userGateway->deleteById($userId);
    }
}
