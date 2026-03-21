<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\RateLimitService;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitServiceTest extends TestCase
{
    // ── check ──

    public function test_check_returns_true_when_under_limit(): void
    {
        RateLimiter::shouldReceive('remaining')
            ->with('test:key', 5)
            ->andReturn(3);

        $result = RateLimitService::check('test:key', 5);
        $this->assertTrue($result);
    }

    public function test_check_returns_false_when_at_limit(): void
    {
        RateLimiter::shouldReceive('remaining')
            ->with('test:key', 5)
            ->andReturn(0);

        $result = RateLimitService::check('test:key', 5);
        $this->assertFalse($result);
    }

    // ── increment ──

    public function test_increment_delegates_to_rate_limiter(): void
    {
        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturn(true);

        $result = RateLimitService::increment('test:key', 10, 60);
        $this->assertTrue($result);
    }

    // ── remaining ──

    public function test_remaining_returns_count(): void
    {
        RateLimiter::shouldReceive('remaining')
            ->with('test:key', 10)
            ->andReturn(7);

        $result = RateLimitService::remaining('test:key', 10);
        $this->assertEquals(7, $result);
    }

    // ── hit ──

    public function test_hit_returns_current_count(): void
    {
        RateLimiter::shouldReceive('hit')
            ->with('test:key', 60)
            ->andReturn(3);

        $result = RateLimitService::hit('test:key', 60);
        $this->assertEquals(3, $result);
    }

    // ── clear / reset ──

    public function test_clear_calls_rate_limiter_clear(): void
    {
        RateLimiter::shouldReceive('clear')
            ->with('test:key')
            ->once();

        RateLimitService::clear('test:key');
    }

    public function test_reset_calls_rate_limiter_clear(): void
    {
        RateLimiter::shouldReceive('clear')
            ->with('test:key')
            ->once();

        RateLimitService::reset('test:key');
    }
}
