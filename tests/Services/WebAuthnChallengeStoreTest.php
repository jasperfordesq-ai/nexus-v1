<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\WebAuthnChallengeStore;

/**
 * WebAuthnChallengeStore Tests
 *
 * Tests WebAuthn challenge management including:
 * - Challenge creation (random, correct length)
 * - Challenge storage and retrieval
 * - Challenge verification (valid, expired, already used)
 * - Challenge cleanup (expired challenges)
 * - Tenant-scoped storage
 * - Multiple concurrent challenges
 * - File-based fallback storage
 * - Challenge consumption (single-use)
 *
 * Note: These tests use the file-based storage backend since Redis
 * is not available in the unit test environment. The file-based backend
 * is the fallback path when Redis is unavailable.
 *
 * @covers \Nexus\Services\WebAuthnChallengeStore
 */
class WebAuthnChallengeStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset static properties to ensure clean state
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);

        $redisAvailableProp = $ref->getProperty('redisAvailable');
        $redisAvailableProp->setAccessible(true);
        $redisAvailableProp->setValue(null, false); // Force file-based storage

        $redisProp = $ref->getProperty('redis');
        $redisProp->setAccessible(true);
        $redisProp->setValue(null, null);

        // Mock TenantContext by setting session
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['tenant_id'] = 99;
    }

    protected function tearDown(): void
    {
        // Clean up any test challenge files
        $cacheDir = sys_get_temp_dir() . '/nexus_webauthn_challenges';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*.json');
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        // Reset static state
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);

        $redisAvailableProp = $ref->getProperty('redisAvailable');
        $redisAvailableProp->setAccessible(true);
        $redisAvailableProp->setValue(null, null);

        $redisProp = $ref->getProperty('redis');
        $redisProp->setAccessible(true);
        $redisProp->setValue(null, null);

        parent::tearDown();
    }

    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(WebAuthnChallengeStore::class));
    }

    public function testStaticMethodsExist(): void
    {
        $methods = [
            'create',
            'get',
            'consume',
            'delete',
            'verify',
            'cleanup',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(WebAuthnChallengeStore::class, $method),
                "Static method {$method} should exist"
            );

            $ref = new \ReflectionMethod(WebAuthnChallengeStore::class, $method);
            $this->assertTrue($ref->isStatic(), "Method {$method} should be static");
        }
    }

    public function testConstants(): void
    {
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);

        $this->assertTrue($ref->hasConstant('CHALLENGE_TTL'));
        $this->assertTrue($ref->hasConstant('KEY_PREFIX'));

        $constants = $ref->getConstants();
        $this->assertEquals(120, $constants['CHALLENGE_TTL']); // 2 minutes
        $this->assertEquals('webauthn:challenge:', $constants['KEY_PREFIX']);
    }

    // =========================================================================
    // CHALLENGE ID GENERATION TESTS
    // =========================================================================

    public function testGenerateChallengeIdLength(): void
    {
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $method = $ref->getMethod('generateChallengeId');
        $method->setAccessible(true);

        $challengeId = $method->invoke(null);

        // 32 random bytes = 64 hex characters
        $this->assertEquals(64, strlen($challengeId));
    }

    public function testGenerateChallengeIdIsHex(): void
    {
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $method = $ref->getMethod('generateChallengeId');
        $method->setAccessible(true);

        $challengeId = $method->invoke(null);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $challengeId);
    }

    public function testGenerateChallengeIdIsUnique(): void
    {
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $method = $ref->getMethod('generateChallengeId');
        $method->setAccessible(true);

        $ids = [];
        for ($i = 0; $i < 50; $i++) {
            $ids[] = $method->invoke(null);
        }

        $uniqueIds = array_unique($ids);
        $this->assertCount(50, $uniqueIds, 'All challenge IDs should be unique');
    }

    // =========================================================================
    // KEY GENERATION TESTS
    // =========================================================================

    public function testGetKeyFormat(): void
    {
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $method = $ref->getMethod('getKey');
        $method->setAccessible(true);

        $key = $method->invoke(null, 'abc123');

        $this->assertEquals('webauthn:challenge:abc123', $key);
    }

    // =========================================================================
    // CREATE METHOD TESTS
    // =========================================================================

    public function testCreateMethodSignature(): void
    {
        $ref = new \ReflectionMethod(WebAuthnChallengeStore::class, 'create');
        $params = $ref->getParameters();

        $this->assertCount(4, $params);
        $this->assertEquals('challenge', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());
        $this->assertEquals('type', $params[2]->getName());
        $this->assertEquals('metadata', $params[3]->getName());

        $this->assertTrue($params[1]->allowsNull());
        $this->assertEquals('authenticate', $params[2]->getDefaultValue());
        $this->assertEquals([], $params[3]->getDefaultValue());
    }

    public function testCreateReturnsChallengeId(): void
    {
        $challengeId = WebAuthnChallengeStore::create('test-challenge', 1, 'register');

        $this->assertIsString($challengeId);
        $this->assertEquals(64, strlen($challengeId)); // Hex-encoded 32 bytes
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $challengeId);
    }

    public function testCreateStoresChallenge(): void
    {
        $challengeId = WebAuthnChallengeStore::create('my-challenge-data', 42, 'authenticate');

        // Verify by retrieving it - this requires TenantContext to return the same tenant
        // Since we can't easily mock TenantContext::getId() for static calls,
        // we verify the file was created instead
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $keyMethod = $ref->getMethod('getKey');
        $keyMethod->setAccessible(true);
        $key = $keyMethod->invoke(null, $challengeId);

        $cacheFileMethod = $ref->getMethod('getCacheFile');
        $cacheFileMethod->setAccessible(true);
        $file = $cacheFileMethod->invoke(null, $key);

        $this->assertFileExists($file);

        // Read and validate stored data
        $data = json_decode(file_get_contents($file), true);
        $this->assertEquals('my-challenge-data', $data['challenge']);
        $this->assertEquals(42, $data['user_id']);
        $this->assertEquals('authenticate', $data['type']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('expires_at', $data);
        $this->assertArrayHasKey('tenant_id', $data);
    }

    public function testCreateWithNullUserId(): void
    {
        $challengeId = WebAuthnChallengeStore::create('challenge', null, 'authenticate');

        $this->assertIsString($challengeId);
        $this->assertNotEmpty($challengeId);

        // Verify stored data has null user_id
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $keyMethod = $ref->getMethod('getKey');
        $keyMethod->setAccessible(true);
        $key = $keyMethod->invoke(null, $challengeId);

        $cacheFileMethod = $ref->getMethod('getCacheFile');
        $cacheFileMethod->setAccessible(true);
        $file = $cacheFileMethod->invoke(null, $key);

        $data = json_decode(file_get_contents($file), true);
        $this->assertNull($data['user_id']);
    }

    public function testCreateWithMetadata(): void
    {
        $metadata = ['ip' => '127.0.0.1', 'user_agent' => 'TestBrowser'];
        $challengeId = WebAuthnChallengeStore::create('challenge', 1, 'register', $metadata);

        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $keyMethod = $ref->getMethod('getKey');
        $keyMethod->setAccessible(true);
        $key = $keyMethod->invoke(null, $challengeId);

        $cacheFileMethod = $ref->getMethod('getCacheFile');
        $cacheFileMethod->setAccessible(true);
        $file = $cacheFileMethod->invoke(null, $key);

        $data = json_decode(file_get_contents($file), true);
        $this->assertEquals($metadata, $data['metadata']);
    }

    public function testCreateSetsExpiryWithinTTL(): void
    {
        $beforeCreate = time();
        $challengeId = WebAuthnChallengeStore::create('challenge', 1);
        $afterCreate = time();

        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $keyMethod = $ref->getMethod('getKey');
        $keyMethod->setAccessible(true);
        $key = $keyMethod->invoke(null, $challengeId);

        $cacheFileMethod = $ref->getMethod('getCacheFile');
        $cacheFileMethod->setAccessible(true);
        $file = $cacheFileMethod->invoke(null, $key);

        $data = json_decode(file_get_contents($file), true);

        $expectedMin = $beforeCreate + 120; // CHALLENGE_TTL = 120
        $expectedMax = $afterCreate + 120;

        $this->assertGreaterThanOrEqual($expectedMin, $data['expires_at']);
        $this->assertLessThanOrEqual($expectedMax, $data['expires_at']);
    }

    // =========================================================================
    // DELETE / CONSUME TESTS
    // =========================================================================

    public function testDeleteRemovesFile(): void
    {
        $challengeId = WebAuthnChallengeStore::create('challenge', 1);

        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $keyMethod = $ref->getMethod('getKey');
        $keyMethod->setAccessible(true);
        $key = $keyMethod->invoke(null, $challengeId);

        $cacheFileMethod = $ref->getMethod('getCacheFile');
        $cacheFileMethod->setAccessible(true);
        $file = $cacheFileMethod->invoke(null, $key);

        // File should exist before delete
        $this->assertFileExists($file);

        // Delete
        $result = WebAuthnChallengeStore::delete($challengeId);
        $this->assertTrue($result);

        // File should be gone
        $this->assertFileDoesNotExist($file);
    }

    public function testConsumeDeletesChallenge(): void
    {
        $challengeId = WebAuthnChallengeStore::create('challenge', 1);

        $result = WebAuthnChallengeStore::consume($challengeId);
        $this->assertTrue($result);

        // Should be gone after consume
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $keyMethod = $ref->getMethod('getKey');
        $keyMethod->setAccessible(true);
        $key = $keyMethod->invoke(null, $challengeId);

        $cacheFileMethod = $ref->getMethod('getCacheFile');
        $cacheFileMethod->setAccessible(true);
        $file = $cacheFileMethod->invoke(null, $key);

        $this->assertFileDoesNotExist($file);
    }

    public function testDeleteNonExistentChallengeReturnsTrue(): void
    {
        // Deleting something that doesn't exist should return true (idempotent)
        $result = WebAuthnChallengeStore::delete('nonexistent_challenge_id');

        $this->assertTrue($result);
    }

    // =========================================================================
    // VERIFY METHOD TESTS
    // =========================================================================

    public function testVerifyMethodSignature(): void
    {
        $ref = new \ReflectionMethod(WebAuthnChallengeStore::class, 'verify');
        $params = $ref->getParameters();

        $this->assertCount(4, $params);
        $this->assertEquals('challengeId', $params[0]->getName());
        $this->assertEquals('expectedChallenge', $params[1]->getName());
        $this->assertEquals('expectedUserId', $params[2]->getName());
        $this->assertEquals('expectedType', $params[3]->getName());

        $this->assertTrue($params[2]->allowsNull());
        $this->assertTrue($params[3]->allowsNull());
    }

    public function testVerifyReturnsValidForCorrectChallenge(): void
    {
        $challenge = 'test-challenge-value';
        $challengeId = WebAuthnChallengeStore::create($challenge, 1, 'register');

        $result = WebAuthnChallengeStore::verify($challengeId, $challenge, 1, 'register');

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('data', $result);
    }

    public function testVerifyReturnsInvalidForWrongChallenge(): void
    {
        $challengeId = WebAuthnChallengeStore::create('real-challenge', 1);

        $result = WebAuthnChallengeStore::verify($challengeId, 'wrong-challenge');

        $this->assertFalse($result['valid']);
        $this->assertEquals('Challenge mismatch', $result['error']);
    }

    public function testVerifyReturnsInvalidForNonExistentChallenge(): void
    {
        $result = WebAuthnChallengeStore::verify('nonexistent', 'challenge');

        $this->assertFalse($result['valid']);
        $this->assertEquals('Challenge not found or expired', $result['error']);
    }

    public function testVerifyReturnsInvalidForWrongUser(): void
    {
        $challengeId = WebAuthnChallengeStore::create('challenge', 1, 'register');

        $result = WebAuthnChallengeStore::verify($challengeId, 'challenge', 999, 'register');

        $this->assertFalse($result['valid']);
        $this->assertEquals('User mismatch', $result['error']);
    }

    public function testVerifyReturnsInvalidForWrongType(): void
    {
        $challengeId = WebAuthnChallengeStore::create('challenge', 1, 'register');

        $result = WebAuthnChallengeStore::verify($challengeId, 'challenge', 1, 'authenticate');

        $this->assertFalse($result['valid']);
        $this->assertEquals('Challenge type mismatch', $result['error']);
    }

    public function testVerifySkipsUserCheckWhenExpectedUserIdIsNull(): void
    {
        $challengeId = WebAuthnChallengeStore::create('challenge', 42, 'authenticate');

        $result = WebAuthnChallengeStore::verify($challengeId, 'challenge', null, 'authenticate');

        $this->assertTrue($result['valid']);
    }

    public function testVerifySkipsTypeCheckWhenExpectedTypeIsNull(): void
    {
        $challengeId = WebAuthnChallengeStore::create('challenge', 1, 'register');

        $result = WebAuthnChallengeStore::verify($challengeId, 'challenge', 1, null);

        $this->assertTrue($result['valid']);
    }

    // =========================================================================
    // EXPIRY TESTS
    // =========================================================================

    public function testExpiredChallengeIsNotRetrievable(): void
    {
        // Create a challenge
        $challengeId = WebAuthnChallengeStore::create('challenge', 1);

        // Manually set the expiry to the past
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $keyMethod = $ref->getMethod('getKey');
        $keyMethod->setAccessible(true);
        $key = $keyMethod->invoke(null, $challengeId);

        $cacheFileMethod = $ref->getMethod('getCacheFile');
        $cacheFileMethod->setAccessible(true);
        $file = $cacheFileMethod->invoke(null, $key);

        $data = json_decode(file_get_contents($file), true);
        $data['expires_at'] = time() - 10; // Expired 10 seconds ago
        file_put_contents($file, json_encode($data));

        // Verify returns null for expired challenge
        $result = WebAuthnChallengeStore::verify($challengeId, 'challenge');

        $this->assertFalse($result['valid']);
        $this->assertEquals('Challenge not found or expired', $result['error']);
    }

    // =========================================================================
    // MULTIPLE CONCURRENT CHALLENGES TESTS
    // =========================================================================

    public function testMultipleConcurrentChallenges(): void
    {
        $challengeIds = [];
        for ($i = 0; $i < 5; $i++) {
            $challengeIds[] = WebAuthnChallengeStore::create("challenge_{$i}", $i + 1, 'authenticate');
        }

        // All should have unique IDs
        $this->assertCount(5, array_unique($challengeIds));

        // Each should be independently retrievable via file check
        foreach ($challengeIds as $index => $id) {
            $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
            $keyMethod = $ref->getMethod('getKey');
            $keyMethod->setAccessible(true);
            $key = $keyMethod->invoke(null, $id);

            $cacheFileMethod = $ref->getMethod('getCacheFile');
            $cacheFileMethod->setAccessible(true);
            $file = $cacheFileMethod->invoke(null, $key);

            $this->assertFileExists($file, "Challenge file {$index} should exist");

            $data = json_decode(file_get_contents($file), true);
            $this->assertEquals("challenge_{$index}", $data['challenge']);
        }
    }

    public function testDeletingOneChallengeDoesNotAffectOthers(): void
    {
        $id1 = WebAuthnChallengeStore::create('challenge_1', 1);
        $id2 = WebAuthnChallengeStore::create('challenge_2', 2);
        $id3 = WebAuthnChallengeStore::create('challenge_3', 3);

        // Delete the middle one
        WebAuthnChallengeStore::delete($id2);

        // Others should still exist
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $keyMethod = $ref->getMethod('getKey');
        $keyMethod->setAccessible(true);

        $cacheFileMethod = $ref->getMethod('getCacheFile');
        $cacheFileMethod->setAccessible(true);

        $file1 = $cacheFileMethod->invoke(null, $keyMethod->invoke(null, $id1));
        $file3 = $cacheFileMethod->invoke(null, $keyMethod->invoke(null, $id3));

        $this->assertFileExists($file1);
        $this->assertFileExists($file3);

        // The deleted one should be gone
        $file2 = $cacheFileMethod->invoke(null, $keyMethod->invoke(null, $id2));
        $this->assertFileDoesNotExist($file2);
    }

    // =========================================================================
    // FILE-BASED STORAGE TESTS
    // =========================================================================

    public function testGetCacheDirReturnsPath(): void
    {
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $method = $ref->getMethod('getCacheDir');
        $method->setAccessible(true);

        $dir = $method->invoke(null);

        $this->assertStringContainsString('nexus_webauthn_challenges', $dir);
    }

    public function testGetCacheFileReturnsJsonFile(): void
    {
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $method = $ref->getMethod('getCacheFile');
        $method->setAccessible(true);

        $file = $method->invoke(null, 'test-key');

        $this->assertStringEndsWith('.json', $file);
    }

    public function testStoreInFileReturnsTrue(): void
    {
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $method = $ref->getMethod('storeInFile');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'test-key-' . time(), [
            'challenge' => 'test',
            'expires_at' => time() + 120,
        ]);

        $this->assertTrue($result);
    }

    // =========================================================================
    // REDIS AVAILABILITY TESTS
    // =========================================================================

    public function testIsRedisAvailableReturnsBool(): void
    {
        $ref = new \ReflectionClass(WebAuthnChallengeStore::class);
        $method = $ref->getMethod('isRedisAvailable');
        $method->setAccessible(true);

        $result = $method->invoke(null);

        $this->assertIsBool($result);
    }
}
