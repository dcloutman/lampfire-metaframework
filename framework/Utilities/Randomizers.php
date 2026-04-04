<?php
/**
 * Utility class for generating random values.
 */
class Randomizers {
    /**
     * Generates a cryptographically random password.
     *
     * The alphabet consists of uppercase and lowercase letters, digits, and special characters.
     *
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
