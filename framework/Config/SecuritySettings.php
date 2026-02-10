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
    private string $pasetoKeyHex;

    /**
     * Reads and validates security configuration from the environment.
     *
     * @throws \RuntimeException When a required variable is missing or invalid.
     */
    public function __construct()
    {
        $this->pasetoKeyHex = $this->requireString('PASETO_KEY');

        if (!ctype_xdigit($this->pasetoKeyHex) || strlen($this->pasetoKeyHex) !== 64) {
            throw new \RuntimeException(
                'PASETO_KEY must be a 64-character hexadecimal string (256 bits). '
                . 'Generate with: php -r "echo bin2hex(random_bytes(32));"'
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
