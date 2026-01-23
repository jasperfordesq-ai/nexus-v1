<?php

namespace Nexus\Core;

/**
 * Simple rate limiter using database storage for login attempt tracking
 *
 * Security: Prevents brute force attacks by limiting login attempts
 */
class RateLimiter
{
    // Maximum attempts before lockout
    private const MAX_ATTEMPTS = 5;

    // Lockout duration in seconds (15 minutes)
    private const LOCKOUT_DURATION = 900;

    // Window for counting attempts in seconds (15 minutes)
    private const ATTEMPT_WINDOW = 900;

    /**
     * Check if an IP/email combination is currently rate limited
     *
     * @param string $identifier Email or IP address
     * @param string $type 'email' or 'ip'
     * @return array ['limited' => bool, 'remaining_attempts' => int, 'retry_after' => int|null]
     */
    public static function check(string $identifier, string $type = 'email'): array
    {
        self::ensureTableExists();
        self::cleanupOldAttempts();

        $db = Database::getInstance();
        $cutoff = date('Y-m-d H:i:s', time() - self::ATTEMPT_WINDOW);

        // Count recent failed attempts
        $stmt = $db->prepare(
            "SELECT COUNT(*) as attempt_count, MAX(attempted_at) as last_attempt
             FROM login_attempts
             WHERE identifier = ? AND type = ? AND attempted_at > ? AND success = 0"
        );
        $stmt->execute([$identifier, $type, $cutoff]);
        $result = $stmt->fetch();

        $attemptCount = (int)($result['attempt_count'] ?? 0);
        $lastAttempt = $result['last_attempt'] ?? null;

        if ($attemptCount >= self::MAX_ATTEMPTS) {
            $lastAttemptTime = $lastAttempt ? strtotime($lastAttempt) : time();
            $lockoutEnd = $lastAttemptTime + self::LOCKOUT_DURATION;
            $retryAfter = $lockoutEnd - time();

            if ($retryAfter > 0) {
                return [
                    'limited' => true,
                    'remaining_attempts' => 0,
                    'retry_after' => $retryAfter
                ];
            }
        }

        return [
            'limited' => false,
            'remaining_attempts' => max(0, self::MAX_ATTEMPTS - $attemptCount),
            'retry_after' => null
        ];
    }

    /**
     * Record a login attempt
     *
     * @param string $identifier Email or IP address
     * @param string $type 'email' or 'ip'
     * @param bool $success Whether the attempt was successful
     */
    public static function recordAttempt(string $identifier, string $type = 'email', bool $success = false): void
    {
        self::ensureTableExists();

        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $stmt = $db->prepare(
            "INSERT INTO login_attempts (identifier, type, ip_address, success, attempted_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$identifier, $type, $ip, $success ? 1 : 0]);

        // On successful login, clear failed attempts for this identifier
        if ($success) {
            self::clearAttempts($identifier, $type);
        }
    }

    /**
     * Clear failed attempts for an identifier (called on successful login)
     */
    public static function clearAttempts(string $identifier, string $type = 'email'): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE identifier = ? AND type = ? AND success = 0");
        $stmt->execute([$identifier, $type]);
    }

    /**
     * Cleanup old attempts to prevent table bloat
     */
    private static function cleanupOldAttempts(): void
    {
        // Only cleanup occasionally (1% of requests)
        if (rand(1, 100) !== 1) {
            return;
        }

        $db = Database::getInstance();
        $cutoff = date('Y-m-d H:i:s', time() - (self::ATTEMPT_WINDOW * 4));
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?");
        $stmt->execute([$cutoff]);
    }

    /**
     * Ensure the login_attempts table exists
     */
    private static function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $db = Database::getInstance();
        $db->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                type ENUM('email', 'ip') NOT NULL DEFAULT 'email',
                ip_address VARCHAR(45) NOT NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                attempted_at DATETIME NOT NULL,
                INDEX idx_identifier_type (identifier, type),
                INDEX idx_attempted_at (attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $checked = true;
    }

    /**
     * Get formatted retry message for users
     */
    public static function getRetryMessage(int $retryAfter): string
    {
        $minutes = ceil($retryAfter / 60);
        if ($minutes <= 1) {
            return "Too many login attempts. Please try again in 1 minute.";
        }
        return "Too many login attempts. Please try again in {$minutes} minutes.";
    }
}
