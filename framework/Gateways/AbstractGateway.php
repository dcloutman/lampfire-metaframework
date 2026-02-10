<?php

declare(strict_types=1);

/**
 * Abstract base for all gateway implementations.
 *
 * Provides shared utilities that any data-access gateway may need,
 * regardless of whether the backing store is a relational database,
 * a remote API, or a file system.
 */

namespace Lampfire\Gateways;

use InvalidArgumentException;
use Lampfire\Utilities\Validators;

abstract class AbstractGateway
{
    /**
     * Validates that a string is a well-formed UUID v4.
     *
     * @param string $value The value to test.
     * @return bool True when the value matches the UUID v4 format.
     */
    protected function isValidUuid(string $value): bool
    {
        return Validators::isValidUuid($value);
    }

    /**
     * Asserts that a value is a valid UUID v4 and throws when it is not.
     *
     * @param string $value     The value to validate.
     * @param string $fieldName The field name used in the error message.
     * @return void
     * @throws InvalidArgumentException When the value is not a valid UUID.
     */
    protected function requireValidUuid(string $value, string $fieldName = 'id'): void
    {
        Validators::requireValidUuid($value, $fieldName);
    }
}
