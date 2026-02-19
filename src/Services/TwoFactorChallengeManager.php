<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\ConfigService;

/**
 * TwoFactorChallengeManager - Server-side storage for 2FA challenge tokens
 *
 * Provides stateless 2FA support by storing challenge tokens server-side
 * instead of in $_SESSION. This allows Bearer-token authenticated clients
 * (mobile apps, SPAs) to complete 2FA verification.
 *
 * Storage backends:
 * 1. Redis (if available) - fastest, with automatic TTL expiry
 * 2. Database fallback - uses two_factor_challenges table
 *
 * Security features:
 * - Cryptographically random tokens (128 chars)
 * - TTL: 300 seconds (5 minutes to enter the code)
 * - Attempt limiting (5 attempts max)
 * - Single-use: tokens are deleted after successful verification
 * - Tenant-scoped to prevent cross-tenant attacks
 *
 * @package Nexus\Services
 */
class TwoFactorChallengeManager
{
    /**
     * Challenge TTL in seconds (5 minutes)
     */
    private const CHALLENGE_TTL = 300;

    /**
     * Maximum verification attempts before token is invalidated
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Key prefix for Redis storage
     */
    private const KEY_PREFIX = 'two_factor:challenge:';

    /**
     * Redis connection (cached)
     */
    private static $redis = null;

    /**
     * Whether Redis is available
     */
    private static ?bool $redisAvailable = null;

    /**
     * Create a new 2FA challenge token
     *
     * Called when login succeeds but 2FA is required.
     *
     * @param int $userId The user who needs to complete 2FA
     * @param array $methods Available 2FA methods (e.g., ['totp', 'backup_code'])
     * @return string The challenge token to return to the client
     */
    public static function create(int $userId, array $methods = ['totp']): string
    {
        $token = self::generateToken();
        $tenantId = TenantContext::getId();

        $data = [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'methods' => $methods,
            'attempts' => 0,
            'max_attempts' => self::MAX_ATTEMPTS,
            'created_at' => time(),
            'expires_at' => time() + self::CHALLENGE_TTL,
        ];

        if (self::isRedisAvailable()) {
            self::storeInRedis($token, $data);
        } else {
            self::storeInDatabase($token, $data);
        }

        // Clean up expired challenges occasionally
        self::cleanup();

        return $token;
    }

    /**
     * Get a challenge by its token
     *
     * @param string $token The challenge token from the client
     * @return array|null Challenge data or null if not found/expired
     */
    public static function get(string $token): ?array
    {
        $tenantId = TenantContext::getId();

        if (self::isRedisAvailable()) {
            $data = self::getFromRedis($token);
        } else {
            $data = self::getFromDatabase($token);
        }

        if ($data === null) {
            return null;
        }

        // Verify tenant scope
        if (($data['tenant_id'] ?? null) !== $tenantId) {
            error_log("[TwoFactorChallengeManager] Tenant mismatch for token");
            return null;
        }

        // Check expiry
        if (time() > ($data['expires_at'] ?? 0)) {
            self::delete($token);
            return null;
        }

        return $data;
    }

    /**
     * Record a verification attempt
     *
     * @param string $token The challenge token
     * @return array{allowed: bool, attempts: int, max_attempts: int}
     */
    public static function recordAttempt(string $token): array
    {
        $data = self::get($token);

        if ($data === null) {
            return ['allowed' => false, 'attempts' => 0, 'max_attempts' => self::MAX_ATTEMPTS];
        }

        $attempts = ($data['attempts'] ?? 0) + 1;
        $maxAttempts = $data['max_attempts'] ?? self::MAX_ATTEMPTS;

        if ($attempts > $maxAttempts) {
            // Too many attempts - delete the token
            self::delete($token);
            return ['allowed' => false, 'attempts' => $attempts, 'max_attempts' => $maxAttempts];
        }

        // Update attempt count
        $data['attempts'] = $attempts;

        if (self::isRedisAvailable()) {
            // Update in Redis with remaining TTL
            $remainingTtl = max(1, $data['expires_at'] - time());
            self::storeInRedis($token, $data, $remainingTtl);
        } else {
            self::updateAttemptsInDatabase($token, $attempts);
        }

        return ['allowed' => true, 'attempts' => $attempts, 'max_attempts' => $maxAttempts];
    }

    /**
     * Consume (delete) a challenge after successful verification
     *
     * @param string $token The challenge token
     * @return bool Whether the token was found and deleted
     */
    public static function consume(string $token): bool
    {
        return self::delete($token);
    }

    /**
     * Delete a challenge token
     *
     * @param string $token The challenge token
     * @return bool Whether deletion was successful
     */
    public static function delete(string $token): bool
    {
        if (self::isRedisAvailable()) {
            return self::deleteFromRedis($token);
        } else {
            return self::deleteFromDatabase($token);
        }
    }

    /**
     * Clean up expired challenges
     *
     * Called automatically during create() to prevent accumulation.
     */
    public static function cleanup(): void
    {
        // Only clean up 5% of the time to reduce overhead
        if (random_int(1, 20) !== 1) {
            return;
        }

        if (self::isRedisAvailable()) {
            // Redis handles expiry via TTL, nothing to do
            return;
        }

        // Clean up expired database entries
        try {
            Database::query(
                "DELETE FROM two_factor_challenges WHERE expires_at < NOW()",
                []
            );
        } catch (\Exception $e) {
            error_log('[TwoFactorChallengeManager] Cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Ensure the database table exists
     *
     * Called automatically when using database storage.
     */
    public static function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            $db = Database::getConnection();
            $db->exec("
                CREATE TABLE IF NOT EXISTS two_factor_challenges (
                    token VARCHAR(128) PRIMARY KEY,
                    user_id INT NOT NULL,
                    tenant_id INT NOT NULL,
                    methods JSON NOT NULL,
                    attempts INT NOT NULL DEFAULT 0,
                    max_attempts INT NOT NULL DEFAULT 5,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NOT NULL,
                    INDEX idx_expires (expires_at),
                    INDEX idx_user (user_id),
                    INDEX idx_tenant (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $checked = true;
        } catch (\Exception $e) {
            error_log('[TwoFactorChallengeManager] Table creation failed: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Generate a cryptographically secure challenge token
     */
    private static function generateToken(): string
    {
        return bin2hex(random_bytes(64)); // 128 character hex string
    }

    /**
     * Get the storage key for a token
     */
    private static function getKey(string $token): string
    {
        return self::KEY_PREFIX . $token;
    }

    /**
     * Check if Redis is available
     */
    private static function isRedisAvailable(): bool
    {
        if (self::$redisAvailable !== null) {
            return self::$redisAvailable;
        }

        if (!class_exists('Redis')) {
            self::$redisAvailable = false;
            return false;
        }

        try {
            $redis = self::getRedis();
            self::$redisAvailable = ($redis !== null);
        } catch (\Exception $e) {
            error_log('[TwoFactorChallengeManager] Redis check failed: ' . $e->getMessage());
            self::$redisAvailable = false;
        }

        return self::$redisAvailable;
    }

    /**
     * Get Redis connection
     */
    private static function getRedis(): ?\Redis
    {
        if (self::$redis !== null) {
            return self::$redis;
        }

        try {
            $config = ConfigService::getInstance()->getRedis();
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 6379;
            $database = $config['database'] ?? 0;
            $password = $config['password'] ?? null;

            $redis = new \Redis();
            $connected = @$redis->connect($host, $port, 2.0);

            if (!$connected) {
                return null;
            }

            if ($password && !empty($password)) {
                if (!$redis->auth($password)) {
                    return null;
                }
            }

            $redis->select($database);
            $redis->setOption(\Redis::OPT_PREFIX, 'nexus:');

            self::$redis = $redis;
            return $redis;
        } catch (\Exception $e) {
            error_log('[TwoFactorChallengeManager] Redis connection failed: ' . $e->getMessage());
            return null;
        }
    }

    // ========================================================================
    // REDIS STORAGE
    // ========================================================================

    private static function storeInRedis(string $token, array $data, ?int $ttl = null): bool
    {
        $redis = self::getRedis();
        if ($redis === null) {
            return false;
        }

        $key = self::getKey($token);
        $ttl = $ttl ?? self::CHALLENGE_TTL;

        return $redis->setex($key, $ttl, json_encode($data));
    }

    private static function getFromRedis(string $token): ?array
    {
        $redis = self::getRedis();
        if ($redis === null) {
            return null;
        }

        $key = self::getKey($token);
        $value = $redis->get($key);

        if ($value === false) {
            return null;
        }

        return json_decode($value, true);
    }

    private static function deleteFromRedis(string $token): bool
    {
        $redis = self::getRedis();
        if ($redis === null) {
            return false;
        }

        $key = self::getKey($token);
        return $redis->del($key) > 0;
    }

    // ========================================================================
    // DATABASE STORAGE (FALLBACK)
    // ========================================================================

    private static function storeInDatabase(string $token, array $data): bool
    {
        self::ensureTableExists();

        try {
            $expiresAt = date('Y-m-d H:i:s', $data['expires_at']);
            $methods = json_encode($data['methods']);

            Database::query(
                "INSERT INTO two_factor_challenges
                    (token, user_id, tenant_id, methods, attempts, max_attempts, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    attempts = VALUES(attempts),
                    expires_at = VALUES(expires_at)",
                [
                    $token,
                    $data['user_id'],
                    $data['tenant_id'],
                    $methods,
                    $data['attempts'],
                    $data['max_attempts'],
                    $expiresAt
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log('[TwoFactorChallengeManager] Database store failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function getFromDatabase(string $token): ?array
    {
        self::ensureTableExists();

        try {
            $stmt = Database::query(
                "SELECT * FROM two_factor_challenges WHERE token = ?",
                [$token]
            );
            $row = $stmt->fetch();

            if (!$row) {
                return null;
            }

            return [
                'user_id' => (int)$row['user_id'],
                'tenant_id' => (int)$row['tenant_id'],
                'methods' => json_decode($row['methods'], true) ?? ['totp'],
                'attempts' => (int)$row['attempts'],
                'max_attempts' => (int)$row['max_attempts'],
                'created_at' => strtotime($row['created_at']),
                'expires_at' => strtotime($row['expires_at']),
            ];
        } catch (\Exception $e) {
            error_log('[TwoFactorChallengeManager] Database get failed: ' . $e->getMessage());
            return null;
        }
    }

    private static function updateAttemptsInDatabase(string $token, int $attempts): bool
    {
        try {
            Database::query(
                "UPDATE two_factor_challenges SET attempts = ? WHERE token = ?",
                [$attempts, $token]
            );
            return true;
        } catch (\Exception $e) {
            error_log('[TwoFactorChallengeManager] Database update failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function deleteFromDatabase(string $token): bool
    {
        try {
            $stmt = Database::query(
                "DELETE FROM two_factor_challenges WHERE token = ?",
                [$token]
            );
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log('[TwoFactorChallengeManager] Database delete failed: ' . $e->getMessage());
            return false;
        }
    }
}
