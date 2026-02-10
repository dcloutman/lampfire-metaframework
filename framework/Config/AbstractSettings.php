<?php

declare(strict_types=1);

/**
 * Base class for typed, validated environment configuration.
 *
 * Subclasses read environment variables via the protected helpers
 * defined here and expose them through typed getters. Application
 * developers extend a concrete subclass to define additional
 * configuration groups; the framework handles registration in
 * the DI container.
 *
 * All environment access uses getenv(). Direct use of the $_ENV
 * superglobal is prohibited throughout the project.
 */

namespace Lampfire\Config;

class AbstractSettings
{
    /**
     * Returns a required string environment variable.
     *
     * @param string $key The environment variable name.
     * @return string The value.
     * @throws \RuntimeException When the variable is missing or empty.
     */
    protected function requireString(string $key): string
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            throw new \RuntimeException(
                sprintf('Required environment variable "%s" is not set or is empty.', $key)
            );
        }

        return $value;
    }

    /**
     * Returns an optional string environment variable or a default.
     *
     * @param string $key     The environment variable name.
     * @param string $default The fallback value.
     * @return string The value or the default.
     */
    protected function optionalString(string $key, string $default = ''): string
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * Returns a required integer environment variable.
     *
     * @param string $key The environment variable name.
     * @return int The parsed integer.
     * @throws \RuntimeException When the variable is missing or not numeric.
     */
    protected function requireInt(string $key): int
    {
        $value = $this->requireString($key);

        if (!ctype_digit($value) && !(str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
            throw new \RuntimeException(
                sprintf('Environment variable "%s" must be an integer, got "%s".', $key, $value)
            );
        }

        return (int) $value;
    }

    /**
     * Returns an optional integer environment variable or a default.
     *
     * @param string $key     The environment variable name.
     * @param int    $default The fallback value.
     * @return int The parsed integer or the default.
     */
    protected function optionalInt(string $key, int $default = 0): int
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        if (!ctype_digit($value) && !(str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
            throw new \RuntimeException(
                sprintf('Environment variable "%s" must be an integer, got "%s".', $key, $value)
            );
        }

        return (int) $value;
    }

    /**
     * Returns a required boolean environment variable.
     *
     * Accepted truthy values: "true", "1", "yes", "on".
     * Accepted falsy values: "false", "0", "no", "off".
     *
     * @param string $key The environment variable name.
     * @return bool The parsed boolean.
     * @throws \RuntimeException When the variable is missing or not a recognized boolean string.
     */
    protected function requireBool(string $key): bool
    {
        $value = $this->requireString($key);

        return $this->parseBool($key, $value);
    }

    /**
     * Returns an optional boolean environment variable or a default.
     *
     * @param string $key     The environment variable name.
     * @param bool   $default The fallback value.
     * @return bool The parsed boolean or the default.
     */
    protected function optionalBool(string $key, bool $default = false): bool
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return $this->parseBool($key, $value);
    }

    /**
     * Parses a string into a boolean, throwing on unrecognized values.
     *
     * @param string $key   The variable name for error messages.
     * @param string $value The raw string value.
     * @return bool The parsed boolean.
     * @throws \RuntimeException When the value is not a recognized boolean string.
     */
    private function parseBool(string $key, string $value): bool
    {
        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['false', '0', 'no', 'off'], true)) {
            return false;
        }

        throw new \RuntimeException(
            sprintf(
                'Environment variable "%s" must be a boolean (true/false/1/0/yes/no/on/off), got "%s".',
                $key,
                $value
            )
        );
    }
}
