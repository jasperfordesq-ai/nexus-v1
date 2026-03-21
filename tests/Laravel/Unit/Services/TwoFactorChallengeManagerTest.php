<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\TwoFactorChallengeManager;
use Illuminate\Support\Facades\Cache;

class TwoFactorChallengeManagerTest extends TestCase
{
    private TwoFactorChallengeManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new TwoFactorChallengeManager();
    }

    public function test_create_returns_token_string(): void
    {
        Cache::shouldReceive('put')->once();

        $token = $this->manager->create(1);

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    public function test_get_returns_data_for_valid_token(): void
    {
        $data = ['user_id' => 1, 'methods' => ['totp'], 'attempts' => 0];
        Cache::shouldReceive('get')->with('2fa_challenge:test_token')->andReturn($data);

        $result = $this->manager->get('test_token');

        $this->assertEquals(1, $result['user_id']);
    }

    public function test_get_returns_null_for_invalid_token(): void
    {
        Cache::shouldReceive('get')->andReturn(null);

        $this->assertNull($this->manager->get('invalid'));
    }

    public function test_recordAttempt_returns_not_allowed_when_expired(): void
    {
        Cache::shouldReceive('get')->andReturn(null);

        $result = $this->manager->recordAttempt('expired_token');

        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['attempts_remaining']);
    }

    public function test_recordAttempt_increments_and_returns_remaining(): void
    {
        $data = ['user_id' => 1, 'methods' => ['totp'], 'attempts' => 0];
        Cache::shouldReceive('get')->andReturn($data);
        Cache::shouldReceive('put')->once();

        $result = $this->manager->recordAttempt('valid_token');

        $this->assertTrue($result['allowed']);
        $this->assertEquals(4, $result['attempts_remaining']);
    }

    public function test_recordAttempt_locks_out_after_max_attempts(): void
    {
        $data = ['user_id' => 1, 'methods' => ['totp'], 'attempts' => 4];
        Cache::shouldReceive('get')->andReturn($data);
        Cache::shouldReceive('forget')->once();

        $result = $this->manager->recordAttempt('token');

        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['attempts_remaining']);
    }

    public function test_consume_deletes_token(): void
    {
        Cache::shouldReceive('forget')->with('2fa_challenge:token')->andReturn(true);

        $this->assertTrue($this->manager->consume('token'));
    }

    public function test_delete_removes_from_cache(): void
    {
        Cache::shouldReceive('forget')->with('2fa_challenge:token')->andReturn(true);

        $this->assertTrue($this->manager->delete('token'));
    }
}
