<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Scheduling;

use App\Core\TenantContext;
use App\Services\CronJobRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: the 01:00 streak-milestone cron awarded badges only when
 * login_streak EXACTLY equalled a milestone. A user whose streak incremented
 * past the milestone before the check (early-morning login in a UTC-east
 * timezone) or during a skipped run permanently missed the badge. The query
 * now matches login_streak >= milestone; awardBadge() dedupes, so users who
 * already hold the badge are an idempotent no-op.
 */
class StreakMilestoneCatchUpTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_whose_streak_passed_a_milestone_still_gets_the_badge(): void
    {
        $tenantId = (int) DB::table('tenants')->where('is_active', 1)->orderBy('id')->value('id');
        $userId = (int) DB::table('users')->where('tenant_id', $tenantId)->orderBy('id')->value('id');
        if (!$tenantId || !$userId) {
            $this->markTestSkipped('Test DB lacks an active tenant/user');
        }

        // Streak already PAST the 7-day milestone (the old == query missed
        // this, and the old code awarded the nonexistent key "streak_7" —
        // real badge key is streak_7d — so the cron never awarded anything).
        DB::table('users')->where('id', $userId)->update(['login_streak' => 9]);
        DB::table('user_badges')->where('user_id', $userId)->where('badge_key', 'streak_7d')->delete();

        try {
            $runner = new CronJobRunner();
            $method = new \ReflectionMethod(CronJobRunner::class, 'gamificationStreakMilestonesInternal');
            $method->setAccessible(true);
            ob_start();
            $method->invoke($runner);
            ob_end_clean();

            $this->assertTrue(
                DB::table('user_badges')->where('user_id', $userId)->where('badge_key', 'streak_7d')->exists(),
                'User with login_streak=9 must receive the missed 7-day streak badge'
            );
        } finally {
            TenantContext::reset();
        }
    }
}
