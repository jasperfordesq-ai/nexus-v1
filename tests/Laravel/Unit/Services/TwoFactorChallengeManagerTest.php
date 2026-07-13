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
        Cache::flush();
        $this->manager = new TwoFactorChallengeManager();
    }

    public function test_create_returns_token_string(): void
    {
        Cache::shouldReceive('put')->once();

        $token = $this->manager->create(1);

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    public function test_create_binds_challenge_to_explicit_tenant(): void
    {
        $authenticationStartedAt = time() - 1;
        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function (string $key, array $data, int $ttl) use ($authenticationStartedAt): bool {
                $this->assertStringStartsWith('2fa_challenge:', $key);
                $this->assertSame(41, $data['user_id']);
                $this->assertSame(999, $data['tenant_id']);
                $this->assertSame(['totp', 'backup_code'], $data['methods']);
                $this->assertSame($authenticationStartedAt, $data['authentication_started_at']);
                $this->assertGreaterThan(time(), $data['expires_at']);
                $this->assertSame(300, $ttl);

                return true;
            });

        $this->manager->create(41, ['totp', 'backup_code'], 999, $authenticationStartedAt);
    }

    public function test_get_returns_data_for_valid_token(): void
    {
        $data = [
            'user_id' => 1,
            'methods' => ['totp'],
            'attempts' => 0,
            'expires_at' => time() + 300,
        ];
        Cache::shouldReceive('get')->with('2fa_challenge:test_token')->andReturn($data);

        $result = $this->manager->get('test_token');

        $this->assertEquals(1, $result['user_id']);
    }

    public function test_get_returns_null_for_invalid_token(): void
    {
        Cache::shouldReceive('get')->andReturn(null);

        $this->assertNull($this->manager->get('invalid'));
    }

    public function test_get_removes_challenge_after_absolute_deadline(): void
    {
        Cache::shouldReceive('get')->andReturn([
            'user_id' => 1,
            'tenant_id' => 2,
            'methods' => ['totp'],
            'attempts' => 0,
            'expires_at' => time() - 1,
        ]);
        Cache::shouldReceive('forget')->with('2fa_challenge:expired')->once()->andReturn(true);

        $this->assertNull($this->manager->get('expired'));
    }

    public function test_recordAttempt_returns_not_allowed_when_expired(): void
    {
        $result = $this->manager->recordAttempt('expired_token');

        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['attempts_remaining']);
    }

    public function test_recordAttempt_increments_and_returns_remaining(): void
    {
        $data = [
            'user_id' => 1,
            'methods' => ['totp'],
            'attempts' => 0,
            'expires_at' => time() + 120,
        ];
        Cache::put('2fa_challenge:valid_token', $data, 120);

        $result = $this->manager->recordAttempt('valid_token');

        $this->assertTrue($result['allowed']);
        $this->assertEquals(4, $result['attempts_remaining']);
        $this->assertSame(1, Cache::get('2fa_challenge:valid_token')['attempts']);
    }

    public function test_recordAttempt_locks_out_after_max_attempts(): void
    {
        $data = [
            'user_id' => 1,
            'methods' => ['totp'],
            'attempts' => 4,
            'expires_at' => time() + 300,
        ];
        Cache::put('2fa_challenge:token', $data, 300);

        $result = $this->manager->recordAttempt('token');

        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['attempts_remaining']);
        $this->assertNull(Cache::get('2fa_challenge:token'));
    }

    public function test_consume_deletes_token(): void
    {
        Cache::put('2fa_challenge:token', ['user_id' => 1], 300);

        $this->assertTrue($this->manager->consume('token'));
        $this->assertNull(Cache::get('2fa_challenge:token'));
    }

    public function test_delete_removes_from_cache(): void
    {
        Cache::put('2fa_challenge:token', ['user_id' => 1], 300);

        $this->assertTrue($this->manager->delete('token'));
        $this->assertNull(Cache::get('2fa_challenge:token'));
    }

    public function test_challenge_mutations_fail_closed_while_the_distributed_lock_is_held(): void
    {
        $token = 'contended-token';
        Cache::put('2fa_challenge:' . $token, [
            'user_id' => 1,
            'tenant_id' => 2,
            'methods' => ['totp'],
            'attempts' => 0,
            'expires_at' => time() + 300,
        ], 300);

        $lock = Cache::lock('2fa_challenge_lock:' . hash('sha256', $token), 5);
        $this->assertTrue($lock->get());

        try {
            $attempt = $this->manager->recordAttempt($token);
            $this->assertFalse($attempt['allowed']);
            $this->assertSame(0, $attempt['attempts_remaining']);
            $this->assertFalse($this->manager->consume($token));
            $this->assertSame(0, Cache::get('2fa_challenge:' . $token)['attempts']);
        } finally {
            $lock->release();
        }

        $this->assertTrue($this->manager->consume($token));
        $this->assertNull(Cache::get('2fa_challenge:' . $token));
    }
}
