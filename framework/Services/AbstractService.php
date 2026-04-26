<?php

declare(strict_types=1);

/**
 * Abstract base for all service layer classes.
 *
 * Provides shared non-validation utility behavior used by concrete services.
 */

namespace Lampfire\Services;

use Ramsey\Uuid\Uuid;

abstract class AbstractService
{
    /**
     * Generates a new UUID v4 string.
     *
     * @return string A freshly generated UUID.
     */
    protected function generateUuid(): string
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * Hashes a plaintext password using Argon2id.
     *
     * @param string $password The plaintext password.
     * @return string The Argon2id hash.
     */
    protected function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
}
