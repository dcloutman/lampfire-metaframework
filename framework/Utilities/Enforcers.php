<?php

declare(strict_types=1);

namespace Lampfire\Utilities;

use InvalidArgumentException;

/**
 * Methods that require a value match certain coditions, otherwise the throw exceptions. These
 * method enforce conditions on values passed to the application. Each enforcer method is implemented
 * as a static method.
 */
class Enforcers
{
    /**
     * Enforces that a value is not empty. Throws an exception if the value is empty.
     *
     * @param string $value The value to check.
     * @param string $name The name of the value, used in the exception message.
     * @throws InvalidArgumentException If the value is empty.
     */
    public static function enforceNotEmpty(string $value, string $name): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("$name cannot be empty.");
        }
    }

    /**
     * Enforces that a value is a valid UUID.
     *
     * @param string $value The value to check.
     * @param string $name The name of the value, used in the exception message.
     * @throws InvalidArgumentException If the value is not a valid UUID.
     */
    public static function enforceValidUuid(string $value, string $name): void
    {
        if (Validators::isValidUuid($value) === false) {
            throw new InvalidArgumentException(
                sprintf('The %s field must be a valid UUID.', $name)
            );
        }
    }

    /**
     * Enforces that a trimmed string meets a minimum length.
     *
     * @param string $value The value to check.
     * @param int $minLength The minimum accepted character length.
     * @param string $name The name of the value, used in the exception message.
     * @throws InvalidArgumentException If the value is too short.
     */
    public static function enforceMinLength(string $value, int $minLength, string $name): void
    {
        if (strlen(trim($value)) < $minLength) {
            throw new InvalidArgumentException(
                sprintf('The %s must be at least %d characters long.', $name, $minLength)
            );
        }
    }

    /**
     * Enforces that a value is a valid email address.
     *
     * @param string $emailAddress The email address to check.
     * @throws InvalidArgumentException If the value is not a valid email address.
     */
    public static function enforceValidEmail(string $emailAddress): void
    {
        if (filter_var($emailAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('The email address is not valid.');
        }
    }
}
