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
     * UUID pattern: 8-4-4-4-12 hex digits separated by hyphens.
     *
     * Supports RFC 4122 versions 1 through 5.
     */
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * Validates that a value is a well-formed UUID.
     *
     * @param string $value The value to test.
     * @return bool True when the value is a valid UUID.
     */
    public static function isValidUuid(string $value): bool
    {
        return preg_match(self::UUID_PATTERN, $value) === 1;
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
