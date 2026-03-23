<?php

declare(strict_types=1);

namespace Lampfire\Utilities;
/**
 * Utility functions for generating certain types of values.
 */
class Generators
{
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
            
        for ($i = 0; $i < $randomStringLength; $i++) {
            $index = random_int(0, strlen($characterPool) - 1);
            $randomString .= $characterPool[$index];
        }

        return $randomString;
    }
}

