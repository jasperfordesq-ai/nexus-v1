<?php

namespace Nexus\Services;

use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\ConfigService;

/**
 * WebAuthnChallengeStore - Server-side storage for WebAuthn challenges
 *
 * Provides stateless WebAuthn support by storing challenges server-side
 * instead of in $_SESSION. This allows Bearer-token authenticated clients
 * (mobile apps, SPAs) to use WebAuthn/passkey authentication.
 *
 * Storage backends:
 * 1. Redis (if available) - fastest, with automatic TTL expiry
 * 2. File-based fallback - uses temp directory with manual expiry
 *
 * Security features:
 * - Cryptographically random challenge IDs (64 chars)
 * - Short TTL (120 seconds) to limit replay window
 * - Single-use: challenges are deleted after verification
 * - Tenant-scoped to prevent cross-tenant attacks
 * - User-bound: challenges can only be verified by the originating user
 *
 * @package Nexus\Services
 */
class WebAuthnChallengeStore
{
    /**
     * Challenge TTL in seconds (2 minutes - WebAuthn best practice)
     */
    private const CHALLENGE_TTL = 120;

    /**
     * Key prefix for Redis storage
     */
    private const KEY_PREFIX = 'webauthn:challenge:';

    /**
     * Redis connection (cached)
     */
    private static $redis = null;

    /**
     * Whether Redis is available
     */
    private static ?bool $redisAvailable = null;

    /**
     * Create a new challenge and store it server-side
     *
     * @param string $challenge The WebAuthn challenge (base64url encoded)
     * @param int|null $userId User ID (null for authentication where user not yet known)
     * @param string $type Challenge type: 'register' or 'authenticate'
     * @param array $metadata Additional metadata to store with the challenge
     * @return string Challenge ID to return to client
     */
    public static function create(string $challenge, ?int $userId, string $type = 'authenticate', array $metadata = []): string
    {
        $challengeId = self::generateChallengeId();
        $tenantId = TenantContext::getId();

        $data = [
            'challenge' => $challenge,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'type' => $type,
            'created_at' => time(),
            'expires_at' => time() + self::CHALLENGE_TTL,
            'metadata' => $metadata,
        ];

        $key = self::getKey($challengeId);

        if (self::isRedisAvailable()) {
            self::storeInRedis($key, $data);
        } else {
            self::storeInFile($key, $data);
        }

        return $challengeId;
    }

    /**
     * Retrieve a challenge by its ID
     *
     * Does NOT delete the challenge - call consume() after successful verification.
     *
     * @param string $challengeId The challenge ID from the client
     * @return array|null Challenge data or null if not found/expired
     */
    public static function get(string $challengeId): ?array
    {
        $key = self::getKey($challengeId);
        $tenantId = TenantContext::getId();

        if (self::isRedisAvailable()) {
            $data = self::getFromRedis($key);
        } else {
            $data = self::getFromFile($key);
        }

        if ($data === null) {
            return null;
        }

        // Verify tenant scope
        if (($data['tenant_id'] ?? null) !== $tenantId) {
            error_log("[WebAuthnChallengeStore] Tenant mismatch for challenge $challengeId");
            return null;
        }

        // Check expiry
        if (time() > ($data['expires_at'] ?? 0)) {
            self::delete($challengeId);
            return null;
        }

        return $data;
    }

    /**
     * Consume (delete) a challenge after successful verification
     *
     * This makes the challenge single-use, preventing replay attacks.
     *
     * @param string $challengeId The challenge ID
     * @return bool Whether the challenge was found and deleted
     */
    public static function consume(string $challengeId): bool
    {
        return self::delete($challengeId);
    }

    /**
     * Delete a challenge
     *
     * @param string $challengeId The challenge ID
     * @return bool Whether deletion was successful
     */
    public static function delete(string $challengeId): bool
    {
        $key = self::getKey($challengeId);

        if (self::isRedisAvailable()) {
            return self::deleteFromRedis($key);
        } else {
            return self::deleteFromFile($key);
        }
    }

    /**
     * Verify a challenge matches expected values
     *
     * @param string $challengeId The challenge ID from the client
     * @param string $expectedChallenge The challenge value to verify against
     * @param int|null $expectedUserId User ID to verify (null to skip user check)
     * @param string|null $expectedType Expected challenge type (null to skip type check)
     * @return array{valid: bool, error?: string, data?: array}
     */
    public static function verify(
        string $challengeId,
        string $expectedChallenge,
        ?int $expectedUserId = null,
        ?string $expectedType = null
    ): array {
        $data = self::get($challengeId);

        if ($data === null) {
            return ['valid' => false, 'error' => 'Challenge not found or expired'];
        }

        // Verify challenge value
        if (!hash_equals($data['challenge'], $expectedChallenge)) {
            return ['valid' => false, 'error' => 'Challenge mismatch'];
        }

        // Verify user if specified
        if ($expectedUserId !== null && $data['user_id'] !== null) {
            if ($data['user_id'] !== $expectedUserId) {
                return ['valid' => false, 'error' => 'User mismatch'];
            }
        }

        // Verify type if specified
        if ($expectedType !== null && ($data['type'] ?? null) !== $expectedType) {
            return ['valid' => false, 'error' => 'Challenge type mismatch'];
        }

        return ['valid' => true, 'data' => $data];
    }

    /**
     * Clean up expired challenges (for file-based storage)
     *
     * Called automatically during create() to prevent accumulation.
     */
    public static function cleanup(): void
    {
        // Only clean up 5% of the time to reduce I/O
        if (random_int(1, 20) !== 1) {
            return;
        }

        if (self::isRedisAvailable()) {
            // Redis handles expiry via TTL, nothing to do
            return;
        }

        $cacheDir = self::getCacheDir();
        if (!is_dir($cacheDir)) {
            return;
        }

        $files = glob($cacheDir . '/*.json');
        $now = time();

        foreach ($files as $file) {
            $data = @json_decode(file_get_contents($file), true);
            if (!$data || !isset($data['expires_at']) || $now > $data['expires_at']) {
                @unlink($file);
            }
        }
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Generate a cryptographically secure challenge ID
     */
    private static function generateChallengeId(): string
    {
        return bin2hex(random_bytes(32)); // 64 character hex string
    }

    /**
     * Get the storage key for a challenge ID
     */
    private static function getKey(string $challengeId): string
    {
        return self::KEY_PREFIX . $challengeId;
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
            error_log('[WebAuthnChallengeStore] Redis check failed: ' . $e->getMessage());
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
            error_log('[WebAuthnChallengeStore] Redis connection failed: ' . $e->getMessage());
            return null;
        }
    }

    // ========================================================================
    // REDIS STORAGE
    // ========================================================================

    private static function storeInRedis(string $key, array $data): bool
    {
        $redis = self::getRedis();
        if ($redis === null) {
            return false;
        }

        return $redis->setex($key, self::CHALLENGE_TTL, json_encode($data));
    }

    private static function getFromRedis(string $key): ?array
    {
        $redis = self::getRedis();
        if ($redis === null) {
            return null;
        }

        $value = $redis->get($key);
        if ($value === false) {
            return null;
        }

        return json_decode($value, true);
    }

    private static function deleteFromRedis(string $key): bool
    {
        $redis = self::getRedis();
        if ($redis === null) {
            return false;
        }

        return $redis->del($key) > 0;
    }

    // ========================================================================
    // FILE-BASED STORAGE (FALLBACK)
    // ========================================================================

    private static function getCacheDir(): string
    {
        $dir = sys_get_temp_dir() . '/nexus_webauthn_challenges';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function getCacheFile(string $key): string
    {
        return self::getCacheDir() . '/' . md5($key) . '.json';
    }

    private static function storeInFile(string $key, array $data): bool
    {
        // Run cleanup occasionally
        self::cleanup();

        $file = self::getCacheFile($key);
        return @file_put_contents($file, json_encode($data), LOCK_EX) !== false;
    }

    private static function getFromFile(string $key): ?array
    {
        $file = self::getCacheFile($key);
        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        return json_decode($content, true);
    }

    private static function deleteFromFile(string $key): bool
    {
        $file = self::getCacheFile($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }
}
