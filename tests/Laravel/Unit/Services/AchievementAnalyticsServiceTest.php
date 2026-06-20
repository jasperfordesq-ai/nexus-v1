<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\AchievementAnalyticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Real-DB tests for AchievementAnalyticsService (gamification analytics).
 *
 * Previously all five methods were markTestIncomplete ("heavy DB query builder
 * mocking needed"). They are now real assertions against the nexus_test DB.
 *
 * Isolation strategy: the default test tenant (id 2) already carries ~900 real
 * users, so the aggregate methods (getOverallStats / getTopEarners /
 * getRarestBadges) can never be asserted exactly against it. Every test instead
 * runs against a freshly-created, otherwise-empty tenant whose only rows are the
 * three users + three badges seeded in setUp(). DatabaseTransactions rolls the
 * whole graph back after each test.
 *
 * Seeded graph (in the isolated tenant):
 *   user A — xp 100, level 6, badges: badge_alpha, badge_beta
 *   user B — xp  40, level 2, badges: badge_alpha
 *   user C — xp   0, level 1, no badges
 *
 * nexus_test stores xp/level/balance as INT, so all amounts here are whole.
 *
 * TenantContext is re-pinned immediately before every service call: the service
 * reads TenantContext::getId(), and factory creates / earlier calls can drift it.
 */
class AchievementAnalyticsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AchievementAnalyticsService $service;

    /** Empty, dedicated tenant so aggregates are deterministic. */
    private int $isolatedTenantId = 99901;

    private int $userAId;
    private int $userBId;
    private int $userCId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AchievementAnalyticsService();

        // Create an isolated tenant (rolled back by DatabaseTransactions).
        // setById() requires the tenants row to exist, so insert it first.
        DB::table('tenants')->updateOrInsert(
            ['id' => $this->isolatedTenantId],
            [
                'name' => 'Achievement Analytics Test Tenant',
                'slug' => 'aas-test-' . $this->isolatedTenantId,
                'domain' => null,
                'is_active' => true,
                'depth' => 0,
                'allows_subtenants' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Three users with known xp / level.
        $userA = User::factory()->forTenant($this->isolatedTenantId)
            ->create(['xp' => 100, 'level' => 6, 'balance' => 0, 'avatar_url' => null]);
        $userB = User::factory()->forTenant($this->isolatedTenantId)
            ->create(['xp' => 40, 'level' => 2, 'balance' => 0, 'avatar_url' => null]);
        $userC = User::factory()->forTenant($this->isolatedTenantId)
            ->create(['xp' => 0, 'level' => 1, 'balance' => 0, 'avatar_url' => null]);

        $this->userAId = (int) $userA->id;
        $this->userBId = (int) $userB->id;
        $this->userCId = (int) $userC->id;

        // Badges: A has alpha+beta, B has alpha, C has none.
        // user_badges.tenant_id mirrors the user's tenant; both columns are used
        // by the service (getTopEarners filters user_badges.tenant_id directly).
        DB::table('user_badges')->insert([
            [
                'tenant_id' => $this->isolatedTenantId,
                'user_id' => $this->userAId,
                'badge_key' => 'badge_alpha',
                'awarded_at' => now(),
            ],
            [
                'tenant_id' => $this->isolatedTenantId,
                'user_id' => $this->userAId,
                'badge_key' => 'badge_beta',
                'awarded_at' => now(),
            ],
            [
                'tenant_id' => $this->isolatedTenantId,
                'user_id' => $this->userBId,
                'badge_key' => 'badge_alpha',
                'awarded_at' => now(),
            ],
        ]);

        // Re-pin: factory creates above drift TenantContext.
        TenantContext::setById($this->isolatedTenantId);
    }

    public function test_getOverallStats_returns_expected_structure(): void
    {
        TenantContext::setById($this->isolatedTenantId);
        $stats = $this->service->getOverallStats();

        // Every documented key is present.
        foreach ([
            'total_xp', 'avg_xp', 'max_xp', 'total_badges', 'users_with_badges',
            'total_users', 'engaged_users', 'advanced_users', 'engagement_rate',
            'level_distribution',
        ] as $key) {
            $this->assertArrayHasKey($key, $stats);
        }

        // Exact aggregates over the seeded graph (100 + 40 + 0 xp).
        $this->assertSame(140, $stats['total_xp']);
        $this->assertSame(100, $stats['max_xp']);
        $this->assertEqualsWithDelta(46.7, $stats['avg_xp'], 0.05);

        $this->assertSame(3, $stats['total_badges']);
        $this->assertSame(2, $stats['users_with_badges']);

        $this->assertSame(3, $stats['total_users']);
        // engaged = xp > 0 (A, B); advanced = level >= 5 (A).
        $this->assertSame(2, $stats['engaged_users']);
        $this->assertSame(1, $stats['advanced_users']);
        // 2 of 3 engaged.
        $this->assertEqualsWithDelta(66.7, (float) $stats['engagement_rate'], 0.05);

        // level_distribution is a list of {level, count}, ordered by level asc.
        $this->assertIsArray($stats['level_distribution']);
        $this->assertCount(3, $stats['level_distribution']);
        $levels = array_map(static fn ($r) => (int) $r['level'], $stats['level_distribution']);
        $this->assertSame([1, 2, 6], $levels);
        foreach ($stats['level_distribution'] as $row) {
            $this->assertArrayHasKey('level', $row);
            $this->assertArrayHasKey('count', $row);
            $this->assertSame(1, (int) $row['count']);
        }
    }

    public function test_getBadgeTrends_returns_array(): void
    {
        TenantContext::setById($this->isolatedTenantId);
        $trends = $this->service->getBadgeTrends(30);

        $this->assertIsArray($trends);
        // All three badges were awarded "now", so there is exactly one date bucket.
        $this->assertCount(1, $trends);
        $this->assertArrayHasKey('date', $trends[0]);
        $this->assertArrayHasKey('count', $trends[0]);
        $this->assertSame(3, (int) $trends[0]['count']);
        $this->assertSame(now()->toDateString(), (string) $trends[0]['date']);
    }

    public function test_getBadgeTrends_excludes_badges_older_than_window(): void
    {
        // Push user B's badge well outside a 7-day window.
        DB::table('user_badges')
            ->where('tenant_id', $this->isolatedTenantId)
            ->where('user_id', $this->userBId)
            ->update(['awarded_at' => now()->subDays(40)]);

        TenantContext::setById($this->isolatedTenantId);
        $trends = $this->service->getBadgeTrends(7);

        $this->assertIsArray($trends);
        // Only user A's two "now" badges fall inside the 7-day window.
        $this->assertCount(1, $trends);
        $this->assertSame(2, (int) $trends[0]['count']);
    }

    public function test_getPopularBadges_returns_array_with_default_limit(): void
    {
        TenantContext::setById($this->isolatedTenantId);
        $popular = $this->service->getPopularBadges();

        $this->assertIsArray($popular);
        $this->assertCount(2, $popular);

        // Ordered by award_count DESC: alpha (2) before beta (1).
        $this->assertSame('badge_alpha', $popular[0]['badge_key']);
        $this->assertSame(2, (int) $popular[0]['award_count']);
        $this->assertSame('badge_beta', $popular[1]['badge_key']);
        $this->assertSame(1, (int) $popular[1]['award_count']);

        // Non-custom keys are enriched with key-as-name + default icon + xp 0.
        foreach ($popular as $badge) {
            $this->assertArrayHasKey('name', $badge);
            $this->assertArrayHasKey('icon', $badge);
            $this->assertArrayHasKey('xp', $badge);
            $this->assertSame($badge['badge_key'], $badge['name']);
            $this->assertSame(0, (int) $badge['xp']);
        }
    }

    public function test_getPopularBadges_respects_limit(): void
    {
        TenantContext::setById($this->isolatedTenantId);
        $popular = $this->service->getPopularBadges(1);

        $this->assertCount(1, $popular);
        // The single most-popular badge is alpha.
        $this->assertSame('badge_alpha', $popular[0]['badge_key']);
    }

    public function test_getRarestBadges_returns_array(): void
    {
        TenantContext::setById($this->isolatedTenantId);
        $rarest = $this->service->getRarestBadges();

        $this->assertIsArray($rarest);
        $this->assertCount(2, $rarest);

        // Ordered by award_count ASC: beta (1) before alpha (2).
        $this->assertSame('badge_beta', $rarest[0]['badge_key']);
        $this->assertSame(1, (int) $rarest[0]['award_count']);
        $this->assertSame('badge_alpha', $rarest[1]['badge_key']);
        $this->assertSame(2, (int) $rarest[1]['award_count']);

        // rarity_percent = award_count / total tenant users (3) * 100.
        $this->assertArrayHasKey('rarity_percent', $rarest[0]);
        $this->assertArrayHasKey('name', $rarest[0]);
        $this->assertArrayHasKey('icon', $rarest[0]);
        $this->assertEqualsWithDelta(33.3, (float) $rarest[0]['rarity_percent'], 0.05);
        $this->assertEqualsWithDelta(66.7, (float) $rarest[1]['rarity_percent'], 0.05);
    }

    public function test_getTopEarners_returns_array(): void
    {
        TenantContext::setById($this->isolatedTenantId);
        $earners = $this->service->getTopEarners();

        $this->assertIsArray($earners);
        $this->assertCount(3, $earners);

        // Ordered by xp DESC: A (100), B (40), C (0).
        $this->assertSame($this->userAId, (int) $earners[0]['id']);
        $this->assertSame(100, (int) $earners[0]['xp']);
        $this->assertSame($this->userBId, (int) $earners[1]['id']);
        $this->assertSame(40, (int) $earners[1]['xp']);
        $this->assertSame($this->userCId, (int) $earners[2]['id']);
        $this->assertSame(0, (int) $earners[2]['xp']);

        // badge_count joined in: A=2, B=1, C=0 (left join → 0, never null).
        $this->assertSame(2, (int) $earners[0]['badge_count']);
        $this->assertSame(1, (int) $earners[1]['badge_count']);
        $this->assertSame(0, (int) $earners[2]['badge_count']);

        // Documented projection columns are present.
        foreach (['id', 'first_name', 'last_name', 'avatar_url', 'xp', 'level', 'badge_count'] as $key) {
            $this->assertArrayHasKey($key, $earners[0]);
        }
    }

    public function test_getTopEarners_respects_limit(): void
    {
        TenantContext::setById($this->isolatedTenantId);
        $earners = $this->service->getTopEarners(2);

        $this->assertCount(2, $earners);
        // Top two by xp are A then B.
        $this->assertSame($this->userAId, (int) $earners[0]['id']);
        $this->assertSame($this->userBId, (int) $earners[1]['id']);
    }
}
