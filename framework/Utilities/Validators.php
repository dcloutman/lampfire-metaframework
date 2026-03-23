<?php

declare(strict_types=1);

namespace Lampfire\Utilities;

use InvalidArgumentException;

/**
 * Centralizes common validation routines.
 *
 * Every validation rule that is shared across controllers, services,
 * and gateways belongs here so that the logic exists in exactly one
 * place.
 */
class Validators
{
    /**
     * UUID v4 pattern: 8-4-4-4-12 hex digits separated by hyphens.
     */
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * Validates that a value is a well-formed UUID v4.
     *
     * @param string $value The value to test.
     * @return bool True when the value is a valid UUID v4.
     */
    public static function isValidUuid(string $value): bool
    {
        return preg_match(self::UUID_PATTERN, $value) === 1;
    }

    /**
     * Asserts that a value is a valid UUID v4 and throws when it is not.
     *
     * @param string $value     The value to validate.
     * @param string $fieldName The field name used in the error message.
     * @return void
     * @deprecated This method is deprecated. Use Enforcers::enforceValidUuid instead.
     * @throws InvalidArgumentException When the value is not a valid UUID.
     */
    public static function requireValidUuid(string $value, string $fieldName = 'id'): void
    {
        Enforcers::enforceValidUuid($value, $fieldName);
    }

    /**
     * Determines if the value is a positive integer.
     *
     * @param integer $value
     * @return boolean
     */
    public static function isPositiveInt(int $value): bool
    {
        return $value > 0;
    }

    /**
     * Determines if the value is a positive integer or zero.
     *
     * @param integer $value
     * @return boolean
     */

    public static function isPositiveOrZeroInt(int $value): bool
    {
        return $value >= 0;
    }
}
