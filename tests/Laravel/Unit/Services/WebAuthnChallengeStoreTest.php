<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\WebAuthnChallengeStore;

class WebAuthnChallengeStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Force file-based storage by resetting redis state
        $reflector = new \ReflectionClass(WebAuthnChallengeStore::class);
        $prop = $reflector->getProperty('redisAvailable');
        $prop->setAccessible(true);
        $prop->setValue(null, false);

        $redisProp = $reflector->getProperty('redis');
        $redisProp->setAccessible(true);
        $redisProp->setValue(null, null);
    }

    public function test_create_returns_challenge_id(): void
    {
        $challengeId = WebAuthnChallengeStore::create('test-challenge', 1, 'register');

        $this->assertIsString($challengeId);
        $this->assertEquals(64, strlen($challengeId));
    }

    public function test_get_returns_stored_data(): void
    {
        $challengeId = WebAuthnChallengeStore::create('my-challenge', 42, 'authenticate');

        $data = WebAuthnChallengeStore::get($challengeId);

        $this->assertNotNull($data);
        $this->assertEquals('my-challenge', $data['challenge']);
        $this->assertEquals(42, $data['user_id']);
        $this->assertEquals('authenticate', $data['type']);
    }

    public function test_get_returns_null_for_nonexistent(): void
    {
        $this->assertNull(WebAuthnChallengeStore::get('nonexistent'));
    }

    public function test_consume_removes_challenge(): void
    {
        $challengeId = WebAuthnChallengeStore::create('consume-test', 1);

        $this->assertTrue(WebAuthnChallengeStore::consume($challengeId));
        $this->assertNull(WebAuthnChallengeStore::get($challengeId));
    }

    public function test_consume_returns_false_for_nonexistent(): void
    {
        $this->assertFalse(WebAuthnChallengeStore::consume('nonexistent'));
    }

    public function test_delete_removes_challenge(): void
    {
        $challengeId = WebAuthnChallengeStore::create('delete-test', 1);

        $this->assertTrue(WebAuthnChallengeStore::delete($challengeId));
        $this->assertNull(WebAuthnChallengeStore::get($challengeId));
    }

    public function test_verify_succeeds_with_matching_data(): void
    {
        $challengeId = WebAuthnChallengeStore::create('verify-test', 10, 'register');

        $result = WebAuthnChallengeStore::verify($challengeId, 'verify-test', 10, 'register');

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_verify_fails_with_wrong_challenge(): void
    {
        $challengeId = WebAuthnChallengeStore::create('correct', 1);

        $result = WebAuthnChallengeStore::verify($challengeId, 'wrong');

        $this->assertFalse($result['valid']);
        $this->assertEquals('Challenge mismatch', $result['error']);
    }

    public function test_verify_fails_with_wrong_user(): void
    {
        $challengeId = WebAuthnChallengeStore::create('test', 1);

        $result = WebAuthnChallengeStore::verify($challengeId, 'test', 999);

        $this->assertFalse($result['valid']);
        $this->assertEquals('User mismatch', $result['error']);
    }

    public function test_verify_fails_with_wrong_type(): void
    {
        $challengeId = WebAuthnChallengeStore::create('test', 1, 'register');

        $result = WebAuthnChallengeStore::verify($challengeId, 'test', 1, 'authenticate');

        $this->assertFalse($result['valid']);
        $this->assertEquals('Challenge type mismatch', $result['error']);
    }

    public function test_verify_fails_for_expired_challenge(): void
    {
        $result = WebAuthnChallengeStore::verify('nonexistent', 'test');

        $this->assertFalse($result['valid']);
        $this->assertEquals('Challenge not found or expired', $result['error']);
    }

    public function test_challenge_ttl_constant(): void
    {
        $this->assertEquals(120, WebAuthnChallengeStore::CHALLENGE_TTL);
    }

    protected function tearDown(): void
    {
        // Cleanup temp files
        WebAuthnChallengeStore::cleanup();
        parent::tearDown();
    }
}
