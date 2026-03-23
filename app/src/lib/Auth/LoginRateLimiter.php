<?php
/**
 * Login Rate Limiter.
 *
 * Provides brute-force protection for the login endpoint by tracking
 * failed login attempts and enforcing temporary lockouts.
 *
 * @package App\Auth
 */

declare(strict_types=1);

namespace App\Auth;

/**
 * Rate limiter for login attempts to prevent brute-force attacks.
 *
 * Tracks failed login attempts by username and IP address, enforcing
 * exponential backoff and temporary lockouts after repeated failures.
 */
class LoginRateLimiter
{
    /**
     * @var string Directory to store rate limit data files.
     */
    private string $storageDir;

    /**
     * @var int Maximum failed attempts before lockout.
     */
    private int $maxAttempts;

    /**
     * @var int Base lockout duration in seconds.
     */
    private int $baseLockoutSeconds;

    /**
     * @var int Time window in seconds for counting attempts.
     */
    private int $windowSeconds;

    /**
     * Creates the rate limiter.
     *
     * @param string $storageDir         Directory to store rate limit data.
     * @param int    $maxAttempts        Max failed attempts before lockout (default: 5).
     * @param int    $baseLockoutSeconds Base lockout duration in seconds (default: 60).
     * @param int    $windowSeconds      Time window for counting attempts (default: 900 = 15 min).
     */
    public function __construct(
        string $storageDir,
        int $maxAttempts = 5,
        int $baseLockoutSeconds = 60,
        int $windowSeconds = 900
    ) {
        $this->storageDir = rtrim($storageDir, '/');
        $this->maxAttempts = $maxAttempts;
        $this->baseLockoutSeconds = $baseLockoutSeconds;
        $this->windowSeconds = $windowSeconds;

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0750, true);
        }
    }

    /**
     * Checks if the given username or IP is currently locked out.
     *
     * @param string $username The username attempting to log in.
     * @param string $ipAddress The IP address of the request.
     * @return array{locked: bool, seconds_remaining: int, message: string}
     */
    public function isLockedOut(string $username, string $ipAddress): array
    {
        $usernameData = $this->getAttemptData($this->getUsernameKey($username));
        $ipData = $this->getAttemptData($this->getIpKey($ipAddress));

        // Check username lockout.
        if ($usernameData['locked_until'] > time()) {
            $remaining = $usernameData['locked_until'] - time();
            return [
                'locked' => true,
                'seconds_remaining' => $remaining,
                'message' => "Account temporarily locked. Try again in {$remaining} seconds.",
            ];
        }

        // Check IP lockout.
        if ($ipData['locked_until'] > time()) {
            $remaining = $ipData['locked_until'] - time();
            return [
                'locked' => true,
                'seconds_remaining' => $remaining,
                'message' => "Too many login attempts from this IP. Try again in {$remaining} seconds.",
            ];
        }

        return [
            'locked' => false,
            'seconds_remaining' => 0,
            'message' => '',
        ];
    }

    /**
     * Records a failed login attempt.
     *
     * @param string $username  The username that failed to log in.
     * @param string $ipAddress The IP address of the request.
     * @return void
     */
    public function recordFailedAttempt(string $username, string $ipAddress): void
    {
        $this->incrementAttempts($this->getUsernameKey($username));
        $this->incrementAttempts($this->getIpKey($ipAddress));
    }

    /**
     * Clears failed attempts for a username after successful login.
     *
     * @param string $username The username that successfully logged in.
     * @return void
     */
    public function clearAttempts(string $username): void
    {
        $file = $this->getFilePath($this->getUsernameKey($username));
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Increments attempt count and applies lockout if threshold exceeded.
     *
     * @param string $key The rate limit key (username or IP).
     * @return void
     */
    private function incrementAttempts(string $key): void
    {
        $data = $this->getAttemptData($key);
        $now = time();

        // Reset if outside the window.
        if ($data['window_start'] < $now - $this->windowSeconds) {
            $data = [
                'attempts' => 0,
                'window_start' => $now,
                'locked_until' => 0,
                'lockout_count' => $data['lockout_count'] ?? 0,
            ];
        }

        $data['attempts']++;

        if ($data['attempts'] >= $this->maxAttempts) {
            // Exponential backoff: double lockout time for each consecutive lockout.
            $lockoutCount = ($data['lockout_count'] ?? 0) + 1;
            $lockoutDuration = $this->baseLockoutSeconds * pow(2, min($lockoutCount - 1, 6));
            $data['locked_until'] = $now + $lockoutDuration;
            $data['lockout_count'] = $lockoutCount;
            $data['attempts'] = 0;
            $data['window_start'] = $now;
        }

        $this->saveAttemptData($key, $data);
    }

    /**
     * Gets attempt data from storage.
     *
     * @param string $key The rate limit key.
     * @return array{attempts: int, window_start: int, locked_until: int, lockout_count: int}
     */
    private function getAttemptData(string $key): array
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return [
                'attempts' => 0,
                'window_start' => time(),
                'locked_until' => 0,
                'lockout_count' => 0,
            ];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [
                'attempts' => 0,
                'window_start' => time(),
                'locked_until' => 0,
                'lockout_count' => 0,
            ];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [
                'attempts' => 0,
                'window_start' => time(),
                'locked_until' => 0,
                'lockout_count' => 0,
            ];
        }

        return [
            'attempts' => (int) ($data['attempts'] ?? 0),
            'window_start' => (int) ($data['window_start'] ?? time()),
            'locked_until' => (int) ($data['locked_until'] ?? 0),
            'lockout_count' => (int) ($data['lockout_count'] ?? 0),
        ];
    }

    /**
     * Saves attempt data to storage.
     *
     * @param string               $key  The rate limit key.
     * @param array<string, mixed> $data The attempt data.
     * @return void
     */
    private function saveAttemptData(string $key, array $data): void
    {
        $file = $this->getFilePath($key);
        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Gets the storage file path for a key.
     *
     * @param string $key The rate limit key.
     * @return string The file path.
     */
    private function getFilePath(string $key): string
    {
        return $this->storageDir . '/' . $key . '.json';
    }

    /**
     * Creates a safe filename key for a username.
     *
     * @param string $username The username.
     * @return string The sanitized key.
     */
    private function getUsernameKey(string $username): string
    {
        return 'user_' . hash('sha256', strtolower($username));
    }

    /**
     * Creates a safe filename key for an IP address.
     *
     * @param string $ipAddress The IP address.
     * @return string The sanitized key.
     */
    private function getIpKey(string $ipAddress): string
    {
        return 'ip_' . hash('sha256', $ipAddress);
    }

    /**
     * Cleans up expired rate limit files (for cron/maintenance).
     *
     * @return int Number of files cleaned up.
     */
    public function cleanup(): int
    {
        $count = 0;
        $files = glob($this->storageDir . '/*.json');

        if ($files === false) {
            return 0;
        }

        $now = time();
        $expiryThreshold = $now - ($this->windowSeconds * 2);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);
            if (!is_array($data)) {
                unlink($file);
                $count++;
                continue;
            }

            $windowStart = (int) ($data['window_start'] ?? 0);
            $lockedUntil = (int) ($data['locked_until'] ?? 0);

            // Remove if both window and lockout have expired.
            if ($windowStart < $expiryThreshold && $lockedUntil < $now) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
