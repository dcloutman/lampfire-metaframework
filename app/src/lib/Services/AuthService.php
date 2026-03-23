<?php

declare(strict_types=1);

/**
 * Authentication service using Paseto v4 local (symmetric) tokens.
 *
 * Handles credential verification, token creation, and token parsing.
 * Tokens are stored in HTTP-only cookies by the controller layer.
 * Passwords are verified against Argon2id hashes stored in the Users table.
 */

namespace App\Services;

use Lampfire\Gateways\UserGateway;
use Lampfire\Config\SecuritySettings;
use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Keys\Version4\SymmetricKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version4;
use ParagonIE\Paseto\ProtocolCollection;
use ParagonIE\Paseto\Rules\NotExpired;

class AuthService
{
    private SymmetricKey $symmetricKey;
    private UserGateway $userGateway;

    /**
     * Creates the authentication service.
     *
     * @param UserGateway      $userGateway Gateway for the Users table.
     * @param SecuritySettings $settings    Security settings containing the Paseto key.
     */
    public function __construct(UserGateway $userGateway, SecuritySettings $settings)
    {
        $this->userGateway = $userGateway;

        $rawKey = hex2bin($settings->getPasetoKeyHex());
        if ($rawKey === false || strlen($rawKey) !== 32) {
            throw new \InvalidArgumentException(
                'PASETO_KEY must be a valid 64-character hex string (256 bits).'
            );
        }
        $this->symmetricKey = new SymmetricKey($rawKey);
    }

    /**
     * Authenticates a user by username and password.
     *
     * Returns the Paseto token string and user metadata on success.
     * Returns null when the credentials are invalid.
     *
     * @param string $username The login username.
     * @param string $password The plaintext password.
     * @return array{token: string, user_id: string, csrf_token: string}|null
     */
    public function authenticate(string $username, string $password): ?array
    {
        $user = $this->userGateway->findByUsernameWithHash($username);

        if ($user === null) {
            return null;
        }

        $isValid = password_verify($password, $user['password_hash']);
        if ($isValid === false) {
            return null;
        }

        $csrfToken = bin2hex(random_bytes(32));
        $tokenString = $this->createToken(
            $user['user_id'],
            $user['username'],
            $csrfToken
        );

        return [
            'token'      => $tokenString,
            'user_id'    => $user['user_id'],
            'csrf_token' => $csrfToken,
        ];
    }

    /**
     * Creates a Paseto v4 local token with embedded claims.
     *
     * @param string $userId    The user identifier.
     * @param string $username  The username.
     * @param string $csrfToken The CSRF token.
     * @return string The encrypted Paseto token string.
     */
    public function createToken(
        string $userId,
        string $username,
        string $csrfToken
    ): string {
        $now        = new \DateTimeImmutable();
        $expiration = $now->modify('+2 hours');

        $token = Builder::getLocal($this->symmetricKey, new Version4());

        $token->setIssuedAt($now);
        $token->setNotBefore($now);
        $token->setExpiration($expiration);
        $token->set('user_id', $userId);
        $token->set('username', $username);
        $token->set('csrf_token', $csrfToken);

        return $token->toString();
    }

    /**
     * Parses and validates a Paseto v4 local token.
     *
     * Returns the decoded claims on success. Returns null when the token
     * is expired, malformed, or otherwise invalid.
     *
     * @param string $tokenString The raw Paseto token.
     * @return array{user_id: string, username: string, csrf_token: string}|null
     */
    public function parseToken(string $tokenString): ?array
    {
        try {
            $parser = Parser::getLocal($this->symmetricKey, ProtocolCollection::v4());
            $parser->addRule(new NotExpired());

            $token = $parser->parse($tokenString);

            return [
                'user_id'    => $token->get('user_id'),
                'username'   => $token->get('username'),
                'csrf_token' => $token->get('csrf_token'),
            ];
        } catch (\Throwable $exception) {
            // The token is invalid, expired, or tampered with.
            return null;
        }
    }
}
