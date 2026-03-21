<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    // -------------------------------------------------------
    // DEFAULT_LIMITS constant
    // -------------------------------------------------------

    public function test_default_limits_contains_expected_keys(): void
    {
        $this->assertArrayHasKey('read', RateLimiter::DEFAULT_LIMITS);
        $this->assertArrayHasKey('write', RateLimiter::DEFAULT_LIMITS);
        $this->assertArrayHasKey('upload', RateLimiter::DEFAULT_LIMITS);
        $this->assertArrayHasKey('auth', RateLimiter::DEFAULT_LIMITS);
        $this->assertArrayHasKey('search', RateLimiter::DEFAULT_LIMITS);
    }

    public function test_default_limits_has_sensible_values(): void
    {
        $this->assertSame(120, RateLimiter::DEFAULT_LIMITS['read']);
        $this->assertSame(60, RateLimiter::DEFAULT_LIMITS['write']);
        $this->assertSame(20, RateLimiter::DEFAULT_LIMITS['upload']);
        $this->assertSame(10, RateLimiter::DEFAULT_LIMITS['auth']);
        $this->assertSame(30, RateLimiter::DEFAULT_LIMITS['search']);
    }

    // -------------------------------------------------------
    // getRetryMessage()
    // -------------------------------------------------------

    public function test_getRetryMessage_one_minute_or_less(): void
    {
        $msg = RateLimiter::getRetryMessage(30);
        $this->assertStringContainsString('1 minute', $msg);
    }

    public function test_getRetryMessage_multiple_minutes(): void
    {
        $msg = RateLimiter::getRetryMessage(180);
        $this->assertStringContainsString('3 minutes', $msg);
    }

    public function test_getRetryMessage_exactly_sixty_seconds(): void
    {
        $msg = RateLimiter::getRetryMessage(60);
        $this->assertStringContainsString('1 minute', $msg);
    }

    public function test_getRetryMessage_rounds_up(): void
    {
        $msg = RateLimiter::getRetryMessage(61);
        $this->assertStringContainsString('2 minutes', $msg);
    }

    // -------------------------------------------------------
    // getApiRateLimitState()
    // -------------------------------------------------------

    public function test_getApiRateLimitState_returns_expected_keys(): void
    {
        $state = RateLimiter::getApiRateLimitState('test:key:' . uniqid(), 100, 60);
        $this->assertArrayHasKey('limit', $state);
        $this->assertArrayHasKey('remaining', $state);
        $this->assertArrayHasKey('reset', $state);
        $this->assertArrayHasKey('window', $state);
    }

    public function test_getApiRateLimitState_remaining_equals_limit_when_no_requests(): void
    {
        $key = 'test:fresh:' . uniqid();
        $state = RateLimiter::getApiRateLimitState($key, 50, 60);
        $this->assertSame(50, $state['limit']);
        $this->assertSame(50, $state['remaining']);
    }

    // -------------------------------------------------------
    // attempt()
    // -------------------------------------------------------

    public function test_attempt_returns_true_when_under_limit(): void
    {
        $key = 'test:attempt:' . uniqid();
        $this->assertTrue(RateLimiter::attempt($key, 10, 60));
    }

    public function test_attempt_decrements_remaining(): void
    {
        $key = 'test:decrement:' . uniqid();
        RateLimiter::attempt($key, 10, 60);
        $state = RateLimiter::getCurrentState();
        $this->assertSame(9, $state['remaining']);
    }

    public function test_attempt_returns_false_when_limit_exhausted(): void
    {
        $key = 'test:exhausted:' . uniqid();
        // Exhaust the limit
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::attempt($key, 3, 60);
        }
        $this->assertFalse(RateLimiter::attempt($key, 3, 60));
    }

    // -------------------------------------------------------
    // getCurrentState()
    // -------------------------------------------------------

    public function test_getCurrentState_returns_null_before_attempt(): void
    {
        // Reset via reflection
        $ref = new \ReflectionClass(RateLimiter::class);
        $prop = $ref->getProperty('currentRateLimitState');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $this->assertNull(RateLimiter::getCurrentState());
    }

    public function test_getCurrentState_returns_array_after_attempt(): void
    {
        $key = 'test:state:' . uniqid();
        RateLimiter::attempt($key, 10, 60);
        $state = RateLimiter::getCurrentState();
        $this->assertIsArray($state);
        $this->assertArrayHasKey('limit', $state);
    }
}
