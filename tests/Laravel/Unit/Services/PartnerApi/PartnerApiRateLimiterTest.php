<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\PartnerApi;

use App\Services\PartnerApi\PartnerApiRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * PartnerApiRateLimiterTest
 *
 * Tests the per-partner cache-based rate limiter.
 * Cache is backed by the array driver in tests so no Redis required.
 * No DB writes are performed, no DatabaseTransactions trait needed.
 */
class PartnerApiRateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush(); // start each test with a clean cache
    }

    // ── single hit under limit ────────────────────────────────────────────────

    public function test_first_hit_is_allowed_and_returns_correct_remaining(): void
    {
        $result = PartnerApiRateLimiter::hit(1001, 10);

        $this->assertTrue($result['allowed']);
        $this->assertSame(10, $result['limit']);
        $this->assertSame(9, $result['remaining']);   // 10 - 1 used
        $this->assertSame(0, $result['retry_after']); // not blocked
    }

    // ── hits accumulate within a window ──────────────────────────────────────

    public function test_remaining_decrements_on_successive_hits(): void
    {
        $limit = 5;

        for ($i = 1; $i <= 3; $i++) {
            $result = PartnerApiRateLimiter::hit(1002, $limit);
            $this->assertTrue($result['allowed'], "Hit {$i} of {$limit} should be allowed");
            $this->assertSame($limit - $i, $result['remaining']);
        }
    }

    // ── exactly at the limit ──────────────────────────────────────────────────

    public function test_hit_at_exact_limit_is_still_allowed(): void
    {
        $limit = 3;

        // Exhaust to the boundary.
        for ($i = 0; $i < $limit - 1; $i++) {
            PartnerApiRateLimiter::hit(1003, $limit);
        }

        // The Nth hit (exactly at limit) must still be allowed.
        $result = PartnerApiRateLimiter::hit(1003, $limit);

        $this->assertTrue($result['allowed'], 'Hit exactly at limit should be allowed');
        $this->assertSame(0, $result['remaining']);
    }

    // ── over the limit ────────────────────────────────────────────────────────

    public function test_hit_over_limit_is_blocked_and_retry_after_is_positive(): void
    {
        $limit = 2;

        // Use up the allowance.
        PartnerApiRateLimiter::hit(1004, $limit);
        PartnerApiRateLimiter::hit(1004, $limit);

        // The 3rd hit must be blocked.
        $result = PartnerApiRateLimiter::hit(1004, $limit);

        $this->assertFalse($result['allowed'], 'Hit over limit should be blocked');
        $this->assertSame(0, $result['remaining']);
        $this->assertGreaterThan(0, $result['retry_after'], 'retry_after must be >0 when blocked');
        $this->assertLessThanOrEqual(60, $result['retry_after'], 'retry_after must be ≤60 seconds');
    }

    // ── retry_after is 0 when allowed ────────────────────────────────────────

    public function test_retry_after_is_zero_when_request_is_allowed(): void
    {
        $result = PartnerApiRateLimiter::hit(1005, 100);

        $this->assertSame(0, $result['retry_after'], 'retry_after must be 0 when request is allowed');
    }

    // ── per-partner isolation ─────────────────────────────────────────────────

    public function test_rate_limit_keys_are_isolated_per_partner(): void
    {
        $limit = 2;

        // Exhaust partner A.
        PartnerApiRateLimiter::hit(2001, $limit);
        PartnerApiRateLimiter::hit(2001, $limit);
        $resultA = PartnerApiRateLimiter::hit(2001, $limit);

        // Partner B should start fresh.
        $resultB = PartnerApiRateLimiter::hit(2002, $limit);

        $this->assertFalse($resultA['allowed'], 'Partner A should be blocked after exceeding limit');
        $this->assertTrue($resultB['allowed'], 'Partner B counter must be independent of partner A');
        $this->assertSame($limit - 1, $resultB['remaining']);
    }

    // ── limit=1 edge case ────────────────────────────────────────────────────

    public function test_limit_of_one_allows_first_hit_and_blocks_second(): void
    {
        $result1 = PartnerApiRateLimiter::hit(3001, 1);
        $result2 = PartnerApiRateLimiter::hit(3001, 1);

        $this->assertTrue($result1['allowed'],  'First hit with limit=1 must be allowed');
        $this->assertFalse($result2['allowed'], 'Second hit with limit=1 must be blocked');
    }

    // ── return-shape completeness ─────────────────────────────────────────────

    public function test_result_contains_all_required_keys(): void
    {
        $result = PartnerApiRateLimiter::hit(4001, 60);

        $this->assertArrayHasKey('allowed',      $result);
        $this->assertArrayHasKey('remaining',    $result);
        $this->assertArrayHasKey('limit',        $result);
        $this->assertArrayHasKey('retry_after',  $result);

        $this->assertIsBool($result['allowed']);
        $this->assertIsInt($result['remaining']);
        $this->assertIsInt($result['limit']);
        $this->assertIsInt($result['retry_after']);
    }
}
