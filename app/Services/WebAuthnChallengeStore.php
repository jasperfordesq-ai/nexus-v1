<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * WebAuthn Challenge Store
 *
 * Manages WebAuthn challenge storage and verification using Redis (primary)
 * or file-based storage (fallback). All methods are static for backward
 * compatibility with existing callers and tests.
 */
class WebAuthnChallengeStore
{
    /** @var int Challenge time-to-live in seconds (2 minutes) */
    public const CHALLENGE_TTL = 120;

    /** @var string Redis key prefix */
    public const KEY_PREFIX = 'webauthn:challenge:';

    /** @var bool|null Whether Redis is available */
    private static ?bool $redisAvailable = null;

    /** @var object|null Redis connection */
    private static $redis = null;

    /**
     * Create and store a new challenge.
     */
    public static function create(string $challenge, ?int $userId, string $type = 'authenticate', array $metadata = []): string
    {
        $challengeId = self::generateChallengeId();
        $key = self::getKey($challengeId);

        $tenantId = null;
        if (isset($_SESSION['tenant_id'])) {
            $tenantId = $_SESSION['tenant_id'];
        }

        $data = [
            'challenge' => $challenge,
            'user_id' => $userId,
            'type' => $type,
            'metadata' => $metadata,
            'tenant_id' => $tenantId,
            'created_at' => time(),
            'expires_at' => time() + self::CHALLENGE_TTL,
        ];

        if (self::isRedisAvailable()) {
            self::storeInRedis($key, $data);
        } else {
            self::storeInFile($key, $data);
        }

        return $challengeId;
    }

    /**
     * Get a stored challenge by ID.
     */
    public static function get(string $challengeId): ?array
    {
        $key = self::getKey($challengeId);

        if (self::isRedisAvailable()) {
            $data = self::getFromRedis($key);
        } else {
            $data = self::getFromFile($key);
        }

        if ($data === null) {
            return null;
        }

        // Check expiry
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            self::delete($challengeId);
            return null;
        }

        return $data;
    }

    /**
     * Consume (retrieve and delete) a challenge.
     */
    public static function consume(string $challengeId): bool
    {
        $data = self::get($challengeId);
        if ($data === null) {
            return false;
        }

        return self::delete($challengeId);
    }

    /**
     * Delete a stored challenge.
     */
    public static function delete(string $challengeId): bool
    {
        $key = self::getKey($challengeId);

        if (self::isRedisAvailable()) {
            self::$redis->del($key);
        } else {
            $file = self::getCacheFile($key);
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        return true;
    }

    /**
     * Verify a challenge against expected values.
     */
    public static function verify(string $challengeId, string $expectedChallenge, ?int $expectedUserId = null, ?string $expectedType = null): array
    {
        $data = self::get($challengeId);

        if ($data === null) {
            return ['valid' => false, 'error' => 'Challenge not found or expired'];
        }

        if ($data['challenge'] !== $expectedChallenge) {
            return ['valid' => false, 'error' => 'Challenge mismatch'];
        }

        if ($expectedUserId !== null && $data['user_id'] !== $expectedUserId) {
            return ['valid' => false, 'error' => 'User mismatch'];
        }

        if ($expectedType !== null && $data['type'] !== $expectedType) {
            return ['valid' => false, 'error' => 'Challenge type mismatch'];
        }

        // Consume the challenge after successful verification
        self::delete($challengeId);

        return ['valid' => true, 'data' => $data];
    }

    /**
     * Clean up expired challenges from file-based storage.
     */
    public static function cleanup(): void
    {
        $dir = self::getCacheDir();
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['expires_at']) && $data['expires_at'] < time()) {
                @unlink($file);
            }
        }
    }

    /**
     * Generate a cryptographically random challenge ID.
     */
    private static function generateChallengeId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get the storage key for a challenge ID.
     */
    private static function getKey(string $challengeId): string
    {
        return self::KEY_PREFIX . $challengeId;
    }

    /**
     * Check if Redis is available.
     */
    private static function isRedisAvailable(): bool
    {
        if (self::$redisAvailable !== null) {
            return self::$redisAvailable;
        }

        try {
            if (class_exists('Redis')) {
                $redis = new \Redis();
                $host = getenv('REDIS_HOST') ?: '127.0.0.1';
                $port = (int) (getenv('REDIS_PORT') ?: 6379);
                $connected = @$redis->connect($host, $port, 1.0);
                if ($connected) {
                    self::$redis = $redis;
                    self::$redisAvailable = true;
                    return true;
                }
            }
        } catch (\Exception $e) {
            // Redis not available
        }

        self::$redisAvailable = false;
        return false;
    }

    /**
     * Store challenge data in Redis.
     */
    private static function storeInRedis(string $key, array $data): void
    {
        self::$redis->setex($key, self::CHALLENGE_TTL, json_encode($data));
    }

    /**
     * Get challenge data from Redis.
     */
    private static function getFromRedis(string $key): ?array
    {
        $raw = self::$redis->get($key);
        if ($raw === false) {
            return null;
        }
        return json_decode($raw, true);
    }

    /**
     * Store challenge data in a file.
     */
    private static function storeInFile(string $key, array $data): bool
    {
        $file = self::getCacheFile($key);
        $dir = dirname($file);

        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return file_put_contents($file, json_encode($data)) !== false;
    }

    /**
     * Get challenge data from a file.
     */
    private static function getFromFile(string $key): ?array
    {
        $file = self::getCacheFile($key);
        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);
        if ($data === null) {
            return null;
        }

        return $data;
    }

    /**
     * Get the cache directory path.
     */
    private static function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nexus_webauthn_challenges';
    }

    /**
     * Get the cache file path for a key.
     */
    private static function getCacheFile(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-:]/', '_', $key);
        return self::getCacheDir() . '/' . $safeKey . '.json';
    }
}
