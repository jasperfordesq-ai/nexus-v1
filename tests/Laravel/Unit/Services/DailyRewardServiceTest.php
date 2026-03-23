<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\DailyRewardService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Mockery;

class DailyRewardServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-03-21 12:00:00'));
        // claim() uses DB::raw() for XP increment
        DB::shouldReceive('raw')->andReturnUsing(fn ($v) => new \Illuminate\Database\Query\Expression($v));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // =========================================================================
    // claim()
    // =========================================================================

    public function test_claim_returns_null_when_already_claimed_today(): void
    {
        DB::shouldReceive('table->where->where->where->exists')->andReturn(true);

        $result = DailyRewardService::claim(2, 1);
        $this->assertNull($result);
    }

    public function test_claim_returns_null_when_user_not_found(): void
    {
        DB::shouldReceive('table->where->where->where->exists')->andReturn(false);
        DB::shouldReceive('table->where->where->select->first')->andReturn(null);

        $result = DailyRewardService::claim(2, 999);
        $this->assertNull($result);
    }

    public function test_claim_starts_new_streak_when_no_previous_reward(): void
    {
        $user = (object) [
            'login_streak' => 0,
            'last_daily_reward' => null,
            'longest_streak' => 0,
            'xp' => 100,
            'level' => 1,
        ];

        DB::shouldReceive('table->where->where->where->exists')->andReturn(false);
        DB::shouldReceive('table->where->where->select->first')->andReturn($user);
        DB::shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());
        DB::shouldReceive('table->insert')->times(2);
        DB::shouldReceive('table->where->where->update')->once();

        $result = DailyRewardService::claim(2, 1);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['streak_day']);
        $this->assertEquals(5, $result['base_xp']);
        $this->assertEquals(0, $result['milestone_bonus']);
        $this->assertEquals(5, $result['xp_earned']);
    }

    public function test_claim_continues_streak_when_last_reward_was_yesterday(): void
    {
        $user = (object) [
            'login_streak' => 2,
            'last_daily_reward' => Carbon::yesterday()->toDateString(),
            'longest_streak' => 5,
            'xp' => 200,
            'level' => 2,
        ];

        DB::shouldReceive('table->where->where->where->exists')->andReturn(false);
        DB::shouldReceive('table->where->where->select->first')->andReturn($user);
        DB::shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());
        DB::shouldReceive('table->insert')->times(2);
        DB::shouldReceive('table->where->where->update')->once();

        $result = DailyRewardService::claim(2, 1);

        $this->assertIsArray($result);
        $this->assertEquals(3, $result['streak_day']);
        $this->assertEquals(5, $result['milestone_bonus']); // 3-day streak bonus
        $this->assertEquals(10, $result['xp_earned']);
    }

    public function test_claim_resets_streak_when_gap_in_days(): void
    {
        $user = (object) [
            'login_streak' => 10,
            'last_daily_reward' => Carbon::now()->subDays(3)->toDateString(),
            'longest_streak' => 10,
            'xp' => 500,
            'level' => 3,
        ];

        DB::shouldReceive('table->where->where->where->exists')->andReturn(false);
        DB::shouldReceive('table->where->where->select->first')->andReturn($user);
        DB::shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());
        DB::shouldReceive('table->insert')->times(2);
        DB::shouldReceive('table->where->where->update')->once();

        $result = DailyRewardService::claim(2, 1);

        $this->assertEquals(1, $result['streak_day']);
        $this->assertEquals(10, $result['longest_streak']); // Keeps previous longest
    }

    // =========================================================================
    // canClaim()
    // =========================================================================

    public function test_canClaim_returns_true_when_not_claimed_today(): void
    {
        DB::shouldReceive('table->where->where->where->exists')->andReturn(false);

        $this->assertTrue(DailyRewardService::canClaim(2, 1));
    }

    public function test_canClaim_returns_false_when_already_claimed(): void
    {
        DB::shouldReceive('table->where->where->where->exists')->andReturn(true);

        $this->assertFalse(DailyRewardService::canClaim(2, 1));
    }

    // =========================================================================
    // getStreak()
    // =========================================================================

    public function test_getStreak_returns_zero_when_user_not_found(): void
    {
        DB::shouldReceive('table->where->where->select->first')->andReturn(null);

        $this->assertEquals(0, DailyRewardService::getStreak(2, 999));
    }

    public function test_getStreak_returns_zero_when_no_previous_reward(): void
    {
        $user = (object) ['login_streak' => 5, 'last_daily_reward' => null];
        DB::shouldReceive('table->where->where->select->first')->andReturn($user);

        $this->assertEquals(0, DailyRewardService::getStreak(2, 1));
    }

    public function test_getStreak_returns_zero_when_streak_broken(): void
    {
        $user = (object) [
            'login_streak' => 5,
            'last_daily_reward' => Carbon::now()->subDays(3)->toDateString(),
        ];
        DB::shouldReceive('table->where->where->select->first')->andReturn($user);

        $this->assertEquals(0, DailyRewardService::getStreak(2, 1));
    }

    // =========================================================================
    // getRewardConfig()
    // =========================================================================

    public function test_getRewardConfig_returns_expected_structure(): void
    {
        $config = DailyRewardService::getRewardConfig(2);

        $this->assertArrayHasKey('base_xp', $config);
        $this->assertArrayHasKey('streak_bonuses', $config);
        $this->assertArrayHasKey('max_streak_bonus', $config);
        $this->assertEquals(5, $config['base_xp']);
        $this->assertEquals(150, $config['max_streak_bonus']);
    }

    // =========================================================================
    // getHistory()
    // =========================================================================

    public function test_getHistory_returns_mapped_array(): void
    {
        $rows = collect([
            (object) ['id' => 1, 'reward_date' => '2026-03-20', 'xp_earned' => 5, 'streak_day' => 1, 'milestone_bonus' => 0, 'claimed_at' => '2026-03-20 10:00:00'],
        ]);

        DB::shouldReceive('table->where->where->orderByDesc->limit->get')->andReturn($rows);

        $result = DailyRewardService::getHistory(1, 30);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals(5, $result[0]['xp_earned']);
    }

    // =========================================================================
    // getTotalEarned()
    // =========================================================================

    public function test_getTotalEarned_returns_sum(): void
    {
        DB::shouldReceive('table->where->where->sum')->andReturn(150);

        $this->assertEquals(150, DailyRewardService::getTotalEarned(1));
    }
}
