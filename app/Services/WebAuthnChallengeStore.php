<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Short-lived, single-use WebAuthn challenge storage.
 *
 * The backend is selected explicitly in config/webauthn.php. It never switches
 * storage backends in response to a runtime failure: doing so can make a
 * challenge written by one PHP worker invisible to the worker that verifies it.
 */
final class WebAuthnChallengeStore
{
    /** Challenge time-to-live in seconds (2 minutes). */
    public const CHALLENGE_TTL = 120;

    /** Redis key prefix retained for backwards compatibility. */
    public const KEY_PREFIX = 'webauthn:challenge:';

    private const CHALLENGE_ID_PATTERN = '/\A[a-f0-9]{64}\z/D';

    private const REDIS_ATOMIC_PULL_SCRIPT = <<<'LUA'
local value = redis.call('GET', KEYS[1])
if value then
    redis.call('DEL', KEYS[1])
end
return value
LUA;

    /**
     * Create and persist a new challenge.
     */
    public static function create(
        string $challenge,
        ?int $userId,
        string $type = 'authenticate',
        array $metadata = []
    ): string {
        $challengeId = self::generateChallengeId();
        $now = time();

        $tenantId = null;
        try {
            $tenantId = TenantContext::getId();
        } catch (Throwable) {
            // Bare unit tests may not have a resolved tenant.
        }
        if ($tenantId === null && isset($_SESSION['tenant_id'])) {
            $tenantId = (int) $_SESSION['tenant_id'];
        }

        $data = [
            'challenge' => $challenge,
            'user_id' => $userId,
            'type' => $type,
            'metadata' => $metadata,
            'tenant_id' => $tenantId,
            'created_at' => $now,
            'expires_at' => $now + self::CHALLENGE_TTL,
        ];

        $key = self::getKey($challengeId);
        $driver = self::driver();
        match ($driver) {
            'redis' => self::storeInRedis($key, $data),
            'file' => self::storeInFile($key, $data),
        };
        if ($driver === 'file') {
            $cleanupEvery = max(1, (int) config('webauthn.challenge_store.file_cleanup_every', 100));
            if (random_int(1, $cleanupEvery) === 1) {
                self::cleanup();
            }
        }

        return $challengeId;
    }

    /**
     * Read a challenge without consuming it.
     */
    public static function get(string $challengeId): ?array
    {
        if (!self::isValidChallengeId($challengeId)) {
            return null;
        }

        $key = self::getKey($challengeId);
        $data = match (self::driver()) {
            'redis' => self::getFromRedis($key),
            'file' => self::getFromFile($key),
        };

        if ($data === null) {
            return null;
        }

        if (self::isExpired($data)) {
            self::delete($challengeId);
            return null;
        }

        return $data;
    }

    /**
     * Atomically retrieve and consume a challenge.
     *
     * Exactly one concurrent caller can receive a given challenge. An expired
     * challenge is still consumed, but is returned as null.
     */
    public static function pull(string $challengeId): ?array
    {
        if (!self::isValidChallengeId($challengeId)) {
            return null;
        }

        $key = self::getKey($challengeId);
        $data = match (self::driver()) {
            'redis' => self::pullFromRedis($key),
            'file' => self::pullFromFile($key),
        };

        if ($data === null || self::isExpired($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Consume a challenge, retaining the historical boolean API.
     */
    public static function consume(string $challengeId): bool
    {
        return self::pull($challengeId) !== null;
    }

    /**
     * Delete a challenge without returning it.
     */
    public static function delete(string $challengeId): bool
    {
        if (!self::isValidChallengeId($challengeId)) {
            return false;
        }

        $key = self::getKey($challengeId);

        return match (self::driver()) {
            'redis' => self::deleteFromRedis($key),
            'file' => self::deleteFromFile($key),
        };
    }

    /**
     * Atomically consume and verify a challenge against expected values.
     *
     * The challenge is deliberately consumed even when an expected value does
     * not match. Retaining a failed ceremony's challenge permits replay and
     * gives an attacker repeated attempts against one authentication event.
     */
    public static function verify(
        string $challengeId,
        string $expectedChallenge,
        ?int $expectedUserId = null,
        ?string $expectedType = null
    ): array {
        $data = self::pull($challengeId);

        if ($data === null) {
            return ['valid' => false, 'error' => 'Challenge not found or expired'];
        }

        if (!hash_equals((string) $data['challenge'], $expectedChallenge)) {
            return ['valid' => false, 'error' => 'Challenge mismatch'];
        }

        if ($expectedUserId !== null && $data['user_id'] !== $expectedUserId) {
            return ['valid' => false, 'error' => 'User mismatch'];
        }

        if ($expectedType !== null && $data['type'] !== $expectedType) {
            return ['valid' => false, 'error' => 'Challenge type mismatch'];
        }

        return ['valid' => true, 'data' => $data];
    }

    /**
     * Remove expired file-backed records and abandoned atomic-claim files.
     */
    public static function cleanup(): void
    {
        if (self::driver() !== 'file') {
            return;
        }

        $directory = self::getCacheDir();
        if (!is_dir($directory)) {
            return;
        }

        $now = time();
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            $raw = file_get_contents($file);
            if ($raw === false) {
                clearstatcache(true, $file);
                if (!is_file($file)) {
                    // An atomic pull claimed the file after glob() returned it.
                    continue;
                }
                throw new RuntimeException('Unable to read a WebAuthn challenge during cleanup.');
            }

            try {
                $data = self::decodeStoredData($raw);
            } catch (RuntimeException) {
                // A corrupt record cannot safely be accepted as a challenge.
                self::unlinkFile($file, 'delete a corrupt WebAuthn challenge');
                continue;
            }

            if (self::isExpired($data)) {
                self::unlinkFile($file, 'delete an expired WebAuthn challenge');
            }
        }

        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.claim.*') ?: [] as $claimFile) {
            $modifiedAt = filemtime($claimFile);
            if ($modifiedAt !== false && $modifiedAt < $now - self::CHALLENGE_TTL) {
                self::unlinkFile($claimFile, 'delete an abandoned WebAuthn challenge claim');
            }
        }
    }

    private static function driver(): string
    {
        $driver = strtolower(trim((string) config('webauthn.challenge_store.driver', 'redis')));
        if (!in_array($driver, ['redis', 'file'], true)) {
            throw new RuntimeException("Unsupported WebAuthn challenge store driver: {$driver}");
        }

        return $driver;
    }

    private static function generateChallengeId(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function isValidChallengeId(string $challengeId): bool
    {
        return preg_match(self::CHALLENGE_ID_PATTERN, $challengeId) === 1;
    }

    private static function getKey(string $challengeId): string
    {
        return self::KEY_PREFIX . $challengeId;
    }

    private static function isExpired(array $data): bool
    {
        return !isset($data['expires_at']) || !is_numeric($data['expires_at']) || (int) $data['expires_at'] <= time();
    }

    private static function encodeStoredData(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode a WebAuthn challenge.', 0, $exception);
        }
    }

    private static function decodeStoredData(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException('WebAuthn challenge storage returned invalid data.');
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('WebAuthn challenge storage contains invalid JSON.', 0, $exception);
        }

        if (!is_array($data)
            || !isset($data['challenge'], $data['type'], $data['created_at'], $data['expires_at'])
            || !is_string($data['challenge'])
            || !is_string($data['type'])
        ) {
            throw new RuntimeException('WebAuthn challenge storage contains an invalid record.');
        }

        return $data;
    }

    private static function redisConnection(): Connection
    {
        $connectionName = trim((string) config('webauthn.challenge_store.redis_connection', 'default'));
        if ($connectionName === '') {
            throw new RuntimeException('The WebAuthn Redis connection is not configured.');
        }

        try {
            return Redis::connection($connectionName);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to connect to the WebAuthn challenge store.', 0, $exception);
        }
    }

    private static function storeInRedis(string $key, array $data): void
    {
        try {
            $stored = self::redisConnection()->setex($key, self::CHALLENGE_TTL, self::encodeStoredData($data));
        } catch (Throwable $exception) {
            throw self::redisFailure('write', $exception);
        }

        if ($stored === false || $stored === null) {
            throw new RuntimeException('The WebAuthn challenge store rejected a write.');
        }
    }

    private static function getFromRedis(string $key): ?array
    {
        try {
            $raw = self::redisConnection()->get($key);
        } catch (Throwable $exception) {
            throw self::redisFailure('read', $exception);
        }

        if ($raw === false || $raw === null) {
            return null;
        }

        return self::decodeStoredData($raw);
    }

    private static function pullFromRedis(string $key): ?array
    {
        $connection = self::redisConnection();

        try {
            $raw = $connection->command('getdel', [$key]);
        } catch (Throwable $getDelException) {
            if (!self::getDelIsUnsupported($getDelException)) {
                throw self::redisFailure('atomically consume', $getDelException);
            }

            try {
                $raw = $connection->eval(self::REDIS_ATOMIC_PULL_SCRIPT, 1, $key);
            } catch (Throwable $luaException) {
                throw self::redisFailure('atomically consume', $luaException);
            }
        }

        if ($raw === false || $raw === null) {
            return null;
        }

        return self::decodeStoredData($raw);
    }

    private static function deleteFromRedis(string $key): bool
    {
        try {
            return (int) self::redisConnection()->del($key) > 0;
        } catch (Throwable $exception) {
            throw self::redisFailure('delete', $exception);
        }
    }

    private static function getDelIsUnsupported(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unknown command')
            || str_contains($message, 'undefined method')
            || str_contains($message, 'not supported')
            || str_contains($message, 'syntax error');
    }

    private static function redisFailure(string $operation, Throwable $exception): RuntimeException
    {
        return new RuntimeException("Unable to {$operation} the WebAuthn challenge in Redis.", 0, $exception);
    }

    private static function storeInFile(string $key, array $data): void
    {
        $file = self::getCacheFile($key);
        $directory = dirname($file);
        self::ensureCacheDir($directory);

        $temporaryFile = tempnam($directory, '.webauthn-');
        if ($temporaryFile === false) {
            throw new RuntimeException('Unable to create a temporary WebAuthn challenge file.');
        }

        try {
            $bytes = file_put_contents($temporaryFile, self::encodeStoredData($data), LOCK_EX);
            if ($bytes === false) {
                throw new RuntimeException('Unable to write a WebAuthn challenge file.');
            }
            if (!chmod($temporaryFile, 0600)) {
                throw new RuntimeException('Unable to secure a WebAuthn challenge file.');
            }
            if (!rename($temporaryFile, $file)) {
                throw new RuntimeException('Unable to publish a WebAuthn challenge file.');
            }
            $temporaryFile = null;
        } finally {
            if (is_string($temporaryFile) && is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    private static function getFromFile(string $key): ?array
    {
        $file = self::getCacheFile($key);
        if (!is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            clearstatcache(true, $file);
            if (!is_file($file)) {
                // An atomic pull won the race after is_file() was checked.
                return null;
            }
            throw new RuntimeException('Unable to read a WebAuthn challenge file.');
        }

        return self::decodeStoredData($raw);
    }

    private static function pullFromFile(string $key): ?array
    {
        $file = self::getCacheFile($key);
        if (!is_file($file)) {
            return null;
        }

        $claimFile = $file . '.claim.' . bin2hex(random_bytes(16));
        if (!@rename($file, $claimFile)) {
            clearstatcache(true, $file);
            if (!is_file($file)) {
                // Another process atomically claimed the challenge first.
                return null;
            }
            throw new RuntimeException('Unable to claim a WebAuthn challenge file.');
        }

        try {
            $raw = file_get_contents($claimFile);
            if ($raw === false) {
                throw new RuntimeException('Unable to read a claimed WebAuthn challenge file.');
            }

            return self::decodeStoredData($raw);
        } finally {
            self::unlinkFile($claimFile, 'delete a claimed WebAuthn challenge');
        }
    }

    private static function deleteFromFile(string $key): bool
    {
        $file = self::getCacheFile($key);
        if (!is_file($file)) {
            return false;
        }

        self::unlinkFile($file, 'delete a WebAuthn challenge');

        return true;
    }

    private static function ensureCacheDir(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the WebAuthn challenge directory.');
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('The WebAuthn challenge directory is not writable.');
        }
    }

    private static function unlinkFile(string $file, string $operation): void
    {
        if (is_file($file) && !@unlink($file)) {
            throw new RuntimeException("Unable to {$operation}.");
        }
    }

    private static function getCacheDir(): string
    {
        $configured = config('webauthn.challenge_store.file_path');
        if (!is_string($configured) || trim($configured) === '') {
            $configured = function_exists('storage_path')
                ? storage_path('framework/cache/webauthn_challenges')
                : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexus_webauthn_challenges';
        }
        $configured = trim($configured);

        if ($configured === '') {
            throw new RuntimeException('The WebAuthn file challenge path is not configured.');
        }

        return rtrim($configured, '\\/');
    }

    private static function getCacheFile(string $key): string
    {
        $safeKey = hash('sha256', $key);

        return self::getCacheDir() . DIRECTORY_SEPARATOR . $safeKey . '.json';
    }
}
