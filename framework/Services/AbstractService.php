<?php

declare(strict_types=1);

/**
 * Abstract base for all service layer classes.
 *
 * Provides shared validation helpers and the UUID generation method
 * so that concrete services do not duplicate input-checking boilerplate.
 */

namespace Lampfire\Services;

use InvalidArgumentException;
use Lampfire\Utilities\Validators;
use Ramsey\Uuid\Uuid;

abstract class AbstractService
{
    /**
     * Generates a new UUID v4 string.
     *
     * @return string A freshly generated UUID.
     */
    protected function generateUuid(): string
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * Validates that a string is a well-formed UUID v4.
     *
     * @param string $value     The value to check.
     * @param string $fieldName The field name used in the error message.
     * @return void
     * @throws InvalidArgumentException When the value is not a valid UUID.
     */
    protected function requireValidUuid(string $value, string $fieldName = 'id'): void
    {
        Validators::requireValidUuid($value, $fieldName);
    }

    /**
     * Validates that a required string meets a minimum length after trimming.
     *
     * @param string $value     The value to check.
     * @param int    $minLength The minimum acceptable length.
     * @param string $fieldName The field name used in the error message.
     * @return void
     * @throws InvalidArgumentException When the value is too short.
     */
    protected function requireMinLength(string $value, int $minLength, string $fieldName): void
    {
        if (strlen(trim($value)) < $minLength) {
            throw new InvalidArgumentException(
                sprintf('The %s must be at least %d characters long.', $fieldName, $minLength)
            );
        }
    }

    /**
     * Validates that a string is a plausible email address.
     *
     * @param string $emailAddress The email address to check.
     * @return void
     * @throws InvalidArgumentException When the email address is invalid.
     */
    protected function requireValidEmail(string $emailAddress): void
    {
        $filtered = filter_var($emailAddress, FILTER_VALIDATE_EMAIL);
        if ($filtered === false) {
            throw new InvalidArgumentException(
                'The email address is not valid.'
            );
        }
    }

    /**
     * Hashes a plaintext password using Argon2id.
     *
     * @param string $password The plaintext password.
     * @return string The Argon2id hash.
     */
    protected function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
}
