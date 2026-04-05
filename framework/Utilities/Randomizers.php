<?php
declare(strict_types=1);

namespace Lampfire\Utilities;

/**
 * Utility class for generating random values.
 */
class Randomizers {
    /**
     * Generates a random string of the specified length.
     * @param int $length Either the desired length of the string or the minimum length.
     * @param int|null $maxLength If specified, the maximum length of the string.
     * @param array <string> $types The character types to include.
     * @return string The generated random string.
     * @throws \InvalidArgumentException When length parameters are invalid.
     */
    public static function generateRandomString(int $length, ?int $maxLength = null, array $types = ['numeric', 'lowercase', 'uppercase']): string
    {
        $allowedTypes = ['numeric', 'lowercase', 'uppercase', 'special'];
        $randomStringLength = 0;
        $randomString = '';
        foreach ($types as $type) {
            if (!in_array($type, $allowedTypes, true)) {
                throw new \InvalidArgumentException("Invalid character type specified: {$type}");
            }
        }

        if ($length <= 0) {
            throw new \InvalidArgumentException('Length must be a positive integer.');
        }
        if ($maxLength !== null && $maxLength < $length) {
            throw new \InvalidArgumentException('Max length must be greater than or equal to length.');
        }

        $numericChars = '0123456789';
        $lowercaseChars = 'abcdefghijklmnopqrstuvwxyz';
        $uppercaseChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $specialChars = '!@#$%^&*()-_=+[]{}|;:,.<>?';
        $characterPool = '';
        if (in_array('numeric', $types, true)) {
            $characterPool .= $numericChars;
        }
        if (in_array('lowercase', $types, true)) {
            $characterPool .= $lowercaseChars;
        }
        if (in_array('uppercase', $types, true)) {
            $characterPool .= $uppercaseChars;
        }
        if (in_array('special', $types, true)) {
            $characterPool .= $specialChars;
        }

        $randomStringLength = random_int($length, $maxLength ?? $length); 
        
        $characterPool = self::shuffleString($characterPool, random_int(20, 50));
        for ($i = 0; $i < $randomStringLength; $i++) {
            $characterPool = self::shuffleString($characterPool, random_int(20, 50));
            $index = random_int(0, strlen($characterPool) - 1);
            $randomString .= $characterPool[$index];
        }

        return $randomString;
    }

    /**
     * Generates a cryptographically random password.
     *
     * The alphabet consists of uppercase and lowercase letters, digits, and special characters.
     *
     * @deprecated Use generateRandomString() with all four character types instead.
     * @param int $minLength The minimum length of the password.
     * @param int $maxLength The maximum length of the password.
     * @return string A random password.
     */
    public static function generatePassword(int $minLength = 24, int $maxLength = 48): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_.,/|+=';
        $length   = random_int($minLength, $maxLength);
        $password = '';

        $alphabet = self::shuffleString($alphabet, random_int(20, 50));

        for ($i = 0; $i < $length; $i++) {
            $alphabet = self::shuffleString($alphabet, random_int(20, 50));
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    /**
     * Returns a cryptographically shuffled copy of a string using Fisher-Yates.
     *
     * @param string $string The string to shuffle.
     * @param int $numShuffles The number of times to shuffle the string.
     * @return string The shuffled string.
     */
    public static function shuffleString(string $string, int $numShuffles = 10): string
    {
        $characters = str_split($string);
        $last       = count($characters) - 1;

        for ($i = $last; $i > 0; $i--) {
            $j = random_int(0, $i);

            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }
}
