<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * RateLimiter - Rate limiting for login attempts and API endpoints
 *
 * Provides two rate limiting mechanisms:
 * 1. Login-specific rate limiting (database-based, for brute force protection)
 * 2. General API rate limiting (cache-based, for request throttling)
 *
 * Security: Prevents brute force attacks and API abuse
 */
class RateLimiter
{
    // ============================================
    // LOGIN RATE LIMITING CONSTANTS
    // ============================================

    // Maximum login attempts before lockout
    private const MAX_ATTEMPTS = 5;

    // Lockout duration in seconds (15 minutes)
    private const LOCKOUT_DURATION = 900;

    // Window for counting attempts in seconds (15 minutes)
    private const ATTEMPT_WINDOW = 900;

    // ============================================
    // API RATE LIMITING CONSTANTS
    // ============================================

    /** Default rate limits by request type (requests per minute) */
    public const DEFAULT_LIMITS = [
        'read' => 120,      // GET requests
        'write' => 60,      // POST/PUT/PATCH/DELETE requests
        'upload' => 20,     // File upload endpoints
        'auth' => 10,       // Authentication endpoints
        'search' => 30,     // Search endpoints (expensive)
    ];

    /** Cache of rate limit state for current request (for headers) */
    private static ?array $currentRateLimitState = null;

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
        if (random_int(1, 100) !== 1) {
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

    // ============================================
    // API RATE LIMITING (Cache-based)
    // ============================================

    /**
     * Check and record an API request attempt
     *
     * This is a simpler, cache-based rate limiter for general API throttling.
     * Uses APCu if available, falls back to file-based storage.
     *
     * @param string $key Unique key for this rate limit (e.g., "api:listings:user:123")
     * @param int $maxAttempts Maximum requests allowed in the window
     * @param int $windowSeconds Time window in seconds
     * @return bool True if request is allowed, false if rate limited
     */
    public static function attempt(string $key, int $maxAttempts = 60, int $windowSeconds = 60): bool
    {
        $state = self::getApiRateLimitState($key, $maxAttempts, $windowSeconds);

        // Store state for header generation
        self::$currentRateLimitState = $state;

        if ($state['remaining'] <= 0) {
            return false;
        }

        // Increment the counter
        self::incrementApiCounter($key, $windowSeconds);

        // Update state after increment
        self::$currentRateLimitState['remaining']--;

        return true;
    }

    /**
     * Get rate limit state without consuming an attempt
     *
     * @param string $key Rate limit key
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $windowSeconds Window duration
     * @return array ['limit' => int, 'remaining' => int, 'reset' => int]
     */
    public static function getApiRateLimitState(string $key, int $maxAttempts = 60, int $windowSeconds = 60): array
    {
        $count = self::getApiCounter($key);
        $windowStart = self::getWindowStart($key, $windowSeconds);
        $resetTime = $windowStart + $windowSeconds;

        return [
            'limit' => $maxAttempts,
            'remaining' => max(0, $maxAttempts - $count),
            'reset' => $resetTime,
            'window' => $windowSeconds,
        ];
    }

    /**
     * Get the current rate limit state from the last attempt() call
     * Used by BaseApiController to add rate limit headers
     *
     * @return array|null ['limit' => int, 'remaining' => int, 'reset' => int] or null if no rate limit applied
     */
    public static function getCurrentState(): ?array
    {
        return self::$currentRateLimitState;
    }

    /**
     * Get the current request count for a key
     */
    private static function getApiCounter(string $key): int
    {
        // Try APCu first (fastest)
        if (function_exists('apcu_fetch')) {
            $count = apcu_fetch($key);
            return $count === false ? 0 : (int) $count;
        }

        // Fall back to file-based storage
        return self::getFileCounter($key);
    }

    /**
     * Increment the request counter for a key
     */
    private static function incrementApiCounter(string $key, int $windowSeconds): void
    {
        // Try APCu first
        if (function_exists('apcu_fetch')) {
            if (apcu_exists($key)) {
                apcu_inc($key);
            } else {
                apcu_store($key, 1, $windowSeconds);
            }
            return;
        }

        // Fall back to file-based storage
        self::incrementFileCounter($key, $windowSeconds);
    }

    /**
     * Get the window start time for a key
     */
    private static function getWindowStart(string $key, int $windowSeconds): int
    {
        $windowKey = $key . ':window';

        if (function_exists('apcu_fetch')) {
            $start = apcu_fetch($windowKey);
            if ($start === false) {
                $start = time();
                apcu_store($windowKey, $start, $windowSeconds);
            }
            return (int) $start;
        }

        return self::getFileWindowStart($key, $windowSeconds);
    }

    // ============================================
    // FILE-BASED RATE LIMITING (Fallback)
    // ============================================

    /**
     * Get rate limit cache directory
     */
    private static function getCacheDir(): string
    {
        $dir = sys_get_temp_dir() . '/nexus_ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Get cache file path for a key
     */
    private static function getCacheFile(string $key): string
    {
        return self::getCacheDir() . '/' . md5($key) . '.json';
    }

    /**
     * Get counter from file storage
     */
    private static function getFileCounter(string $key): int
    {
        $file = self::getCacheFile($key);
        if (!file_exists($file)) {
            return 0;
        }

        $data = @json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['count']) || !isset($data['expires'])) {
            return 0;
        }

        // Check if expired
        if (time() > $data['expires']) {
            @unlink($file);
            return 0;
        }

        return (int) $data['count'];
    }

    /**
     * Increment counter in file storage
     */
    private static function incrementFileCounter(string $key, int $windowSeconds): void
    {
        $file = self::getCacheFile($key);
        $data = ['count' => 0, 'expires' => time() + $windowSeconds, 'window_start' => time()];

        if (file_exists($file)) {
            $existing = @json_decode(file_get_contents($file), true);
            if ($existing && isset($existing['expires']) && time() <= $existing['expires']) {
                $data = $existing;
            }
        }

        $data['count']++;
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Get window start from file storage
     */
    private static function getFileWindowStart(string $key, int $windowSeconds): int
    {
        $file = self::getCacheFile($key);
        if (!file_exists($file)) {
            return time();
        }

        $data = @json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['window_start']) || !isset($data['expires'])) {
            return time();
        }

        // Check if expired
        if (time() > $data['expires']) {
            return time();
        }

        return (int) $data['window_start'];
    }

    /**
     * Clean up old rate limit cache files (called occasionally)
     */
    public static function cleanupApiCache(): void
    {
        // Only run 1% of the time
        if (random_int(1, 100) !== 1) {
            return;
        }

        $dir = self::getCacheDir();
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*.json');
        $now = time();

        foreach ($files as $file) {
            $data = @json_decode(file_get_contents($file), true);
            if (!$data || !isset($data['expires']) || $now > $data['expires']) {
                @unlink($file);
            }
        }
    }
}
