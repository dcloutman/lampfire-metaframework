<?php

declare(strict_types=1);

/**
 * Cryptographic and authentication configuration.
 *
 * Reads security-sensitive values from the environment. Services
 * that need the Paseto key or other secrets should inject this
 * object rather than accessing the environment directly.
 */

namespace Lampfire\Config;

class SecuritySettings extends AbstractSettings
{
    /**
     * The minimum length of an application account's password.
     */
    public const MINIMUM_PASSWORD_LENGTH = 12;

    /**
     * The minimum length of an application account's username.
     */
    public const MINIMUM_USERNAME_LENGTH = 6;

    /**
     * The secret key used to encrypt and decrypt every session token the application issues.
     *
     * An attacker who obtains this value can create valid session tokens for any user account.
     * This value is read from the PASETO_KEY environment variable and must never be logged
     * or exposed through any interface.
     */
    private string $pasetoKeyHex;

    /**
     * Reads and validates security configuration from the environment.
     *
     * @throws \RuntimeException When a required variable is missing or invalid.
     */
    public function __construct()
    {
        $this->pasetoKeyHex = $this->getRequiredEnvironmentString('PASETO_KEY');

        if (!ctype_xdigit($this->pasetoKeyHex) || strlen($this->pasetoKeyHex) !== 64) {
            throw new \RuntimeException(
                'PASETO_KEY must be a 64-character hexadecimal string (256 bits).  Generate with: php -r "echo bin2hex(random_bytes(32));"'
            );
        }
    }

    /**
     * Returns the Paseto symmetric key as a 64-character hex string.
     */
    public function getPasetoKeyHex(): string
    {
        return $this->pasetoKeyHex;
    }
}
