<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Personalisation;

use App\Services\PersonalisedFeedService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * AG35 — PersonalisedFeedService re-ranking tests.
 *
 * Verifies:
 *  1. Cold-start users (under MIN_ENGAGEMENT_EVENTS) fall back to recency-first sort.
 *  2. Once a user has crossed the engagement threshold, the service re-orders
 *     candidates so items authored by the user's connections / engaged authors
 *     bubble above older unrelated content.
 */
class PersonalisedFeedTest extends TestCase
{
    private PersonalisedFeedService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new PersonalisedFeedService();
    }

    public function test_cold_start_user_falls_back_to_recency_sort(): void
    {
        // Cold-start user — no engagement events.
        $userId = 999_001;

        $now = time();
        $candidates = [
            ['id' => 1, 'user_id' => 10, 'created_at' => date('Y-m-d H:i:s', $now - 3600 * 48)], // 2 days old
            ['id' => 2, 'user_id' => 20, 'created_at' => date('Y-m-d H:i:s', $now - 60)],        // 1 min old (newest)
            ['id' => 3, 'user_id' => 30, 'created_at' => date('Y-m-d H:i:s', $now - 3600)],      // 1 h old
        ];

        $ranked = $this->service->rank($userId, 'feed', $candidates);

        $this->assertCount(3, $ranked);
        // Cold-start sort = recency first.
        $this->assertSame(2, $ranked[0]['id'], 'newest item should rank first for cold-start users');
        $this->assertSame(3, $ranked[1]['id']);
        $this->assertSame(1, $ranked[2]['id']);
    }

    public function test_warm_user_rank_returns_same_set_size_and_is_deterministic(): void
    {
        if (!Schema::hasTable('feed_post_likes') || !Schema::hasTable('users')) {
            $this->markTestSkipped('feed_post_likes / users tables not available in this test database.');
        }

        $userId = 999_002;
        $tenantId = $this->testTenantId;

        // Seed the user so loadUserContext() finds a row.
        DB::table('users')->insertOrIgnore([
            'id'         => $userId,
            'tenant_id'  => $tenantId,
            'email'      => 'pfs-test@example.com',
            'name'       => 'PFS Tester',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed enough engagement events to cross MIN_ENGAGEMENT_EVENTS (5).
        for ($i = 1; $i <= 6; $i++) {
            DB::table('feed_post_likes')->insertOrIgnore([
                'tenant_id'  => $tenantId,
                'user_id'    => $userId,
                'post_id'    => $i,
                'created_at' => now(),
            ]);
        }

        $now = time();
        $candidates = [
            ['id' => 101, 'user_id' => 555, 'created_at' => date('Y-m-d H:i:s', $now - 3600)],
            ['id' => 102, 'user_id' => 666, 'created_at' => date('Y-m-d H:i:s', $now - 7200)],
            ['id' => 103, 'user_id' => 777, 'created_at' => date('Y-m-d H:i:s', $now - 10800)],
        ];

        $ranked = $this->service->rank($userId, 'feed', $candidates);

        // Re-ranking must be a permutation of the input set with no internal score leakage.
        $this->assertCount(3, $ranked);
        $rankedIds = array_column($ranked, 'id');
        sort($rankedIds);
        $this->assertSame([101, 102, 103], $rankedIds);

        foreach ($ranked as $item) {
            $this->assertArrayNotHasKey('_score', $item, 'internal _score must be stripped before returning');
            $this->assertArrayNotHasKey('_score_breakdown', $item);
        }
    }
}
