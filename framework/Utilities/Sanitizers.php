<?php
/**
 * A sanitizer takes a raw value and returns a safe-to-use value. Each sanitizer method is
 * implemented as a static method that accepts the raw value and returns the sanitized value.
 * This class cannot be instantiated and should only be used as a collection of static methods.
 */
class Sanitizers
{
    /**
     * Sanitizes a string by trimming whitespace and stripping HTML tags.
     *
     * @param string $value The raw string value to sanitize.
     * @return string The sanitized string.
     */
    public static function sanitizeString(string $value): string
    {
        return trim(strip_tags($value));
    }

    /**
     * Sanitizes an email address by trimming whitespace and validating the format.
     *
     * @param string $value The raw email address to sanitize.
     * @return string The sanitized email address.
     * @throws InvalidArgumentException If the email address is not valid.
     */
    public static function sanitizeEmail(string $value): string
    {
        $sanitized = filter_var(trim($value), FILTER_SANITIZE_EMAIL);
        if (filter_var($sanitized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid email address format.');
        }
        return $sanitized;
    }

    public static function intOrStrIntToInt(string|int $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $validated = filter_var($value, FILTER_VALIDATE_INT);

            if ($validated !== false) {
                return $validated;
            }
        }

        throw new InvalidArgumentException('Value must be an integer or a string representing an integer.');
    }
}