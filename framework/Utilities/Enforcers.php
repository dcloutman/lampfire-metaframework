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
     * Enforces that a value is a valid UUID v4. Throws an exception if the value is not a valid UUID.
     *
     * @param string $value The value to check.
     * @param string $name The name of the value, used in the exception message.
     * @throws InvalidArgumentException If the value is not a valid UUID.
     */
    public static function enforceValidUuid(string $value, string $name): void
    {
        if (Validators::isValidUuid($value) === false) {
            throw new InvalidArgumentException(
                sprintf('The %s field must be a valid UUID v4.', $name)
            );
        }
    }
}
