<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\TwoFactorChallengeManager;
use Illuminate\Support\Facades\Cache;

/**
 * TwoFactorChallengeManager Tests
 */
class TwoFactorChallengeManagerTest extends TestCase
{
    private TwoFactorChallengeManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new TwoFactorChallengeManager();
        // Use array cache driver for testing
        Cache::driver('array');
    }

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(TwoFactorChallengeManager::class));
    }

    public function test_public_methods_exist(): void
    {
        $methods = ['create', 'get', 'recordAttempt', 'consume', 'delete'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(TwoFactorChallengeManager::class, $method),
                "Method {$method} should exist on TwoFactorChallengeManager"
            );
        }
    }

    public function test_create_returns_string_token(): void
    {
        $token = $this->manager->create(42);
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    public function test_create_stores_challenge_data(): void
    {
        $token = $this->manager->create(42, ['totp', 'backup_code']);
        $data = $this->manager->get($token);

        $this->assertNotNull($data);
        $this->assertIsArray($data);
        $this->assertEquals(42, $data['user_id']);
        $this->assertEquals(['totp', 'backup_code'], $data['methods']);
        $this->assertEquals(0, $data['attempts']);
        $this->assertArrayHasKey('created_at', $data);
    }

    public function test_create_default_methods_is_totp(): void
    {
        $token = $this->manager->create(1);
        $data = $this->manager->get($token);

        $this->assertNotNull($data);
        $this->assertEquals(['totp'], $data['methods']);
    }

    public function test_get_returns_null_for_invalid_token(): void
    {
        $result = $this->manager->get('nonexistent-token-abc123');
        $this->assertNull($result);
    }

    public function test_record_attempt_increments_count(): void
    {
        $token = $this->manager->create(42);

        $result = $this->manager->recordAttempt($token);
        $this->assertTrue($result['allowed']);
        $this->assertEquals(4, $result['attempts_remaining']);

        $data = $this->manager->get($token);
        $this->assertEquals(1, $data['attempts']);
    }

    public function test_record_attempt_returns_not_allowed_after_max(): void
    {
        $token = $this->manager->create(42);

        // Use up all 5 attempts
        for ($i = 0; $i < 4; $i++) {
            $result = $this->manager->recordAttempt($token);
            $this->assertTrue($result['allowed']);
        }

        // 5th attempt should exceed max and delete the challenge
        $result = $this->manager->recordAttempt($token);
        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['attempts_remaining']);

        // Token should be deleted now
        $this->assertNull($this->manager->get($token));
    }

    public function test_record_attempt_returns_not_allowed_for_invalid_token(): void
    {
        $result = $this->manager->recordAttempt('nonexistent-token');
        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['attempts_remaining']);
    }

    public function test_consume_deletes_token(): void
    {
        $token = $this->manager->create(42);
        $this->assertNotNull($this->manager->get($token));

        $result = $this->manager->consume($token);
        $this->assertTrue($result);
        $this->assertNull($this->manager->get($token));
    }

    public function test_delete_removes_token(): void
    {
        $token = $this->manager->create(42);
        $this->assertNotNull($this->manager->get($token));

        $result = $this->manager->delete($token);
        $this->assertTrue($result);
        $this->assertNull($this->manager->get($token));
    }

    public function test_each_create_generates_unique_token(): void
    {
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $tokens[] = $this->manager->create($i + 1);
        }

        // All tokens should be unique
        $this->assertCount(10, array_unique($tokens));
    }

    public function test_create_signature(): void
    {
        $ref = new \ReflectionMethod(TwoFactorChallengeManager::class, 'create');
        $params = $ref->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('methods', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertEquals(['totp'], $params[1]->getDefaultValue());
        $this->assertEquals('string', $ref->getReturnType()->getName());
    }

    public function test_record_attempt_signature(): void
    {
        $ref = new \ReflectionMethod(TwoFactorChallengeManager::class, 'recordAttempt');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('token', $params[0]->getName());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }
}
