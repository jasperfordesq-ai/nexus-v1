<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Scheduling;

use App\Core\TenantContext;
use App\Services\LeaderboardSeasonService;
use App\Services\RecurringShiftService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Real-database regression tests for recurrence/season time-boundary bugs
 * found by the 2026-06-12 runtime bug hunt.
 *
 * Bug history:
 *  - RecurringShiftService clamped the recurrence ANCHOR to today
 *    (max(start_date, today)), so a monthly pattern whose start_date had
 *    passed matched "today's day-of-month" on every daily cron run — one
 *    spurious shift per day — and biweekly week-parity rolled with the
 *    anchor, firing on the wrong weeks.
 *  - EventService::generateOccurrences used naive mutable "+1 month", so a
 *    month-end series overflowed (May 31 → Jul 1) and permanently drifted
 *    to the 1st, skipping months entirely.
 *  - LeaderboardSeasonService::getCurrentSeason compared the DATE column
 *    end_date against a full datetime, hiding the season for its whole final
 *    day; getOrCreateCurrentSeason then inserted a DUPLICATE season per call
 *    (observed live: 6 duplicate "March 2026" rows on tenant 2), and the
 *    nightly finalizer awards season rewards once per duplicate row.
 */
class RecurringScheduleRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('tenants')->where('is_active', 1)->orderBy('id')->value('id');
        $this->userId = (int) DB::table('users')->where('tenant_id', $this->tenantId)->orderBy('id')->value('id');
        if (!$this->tenantId || !$this->userId) {
            $this->markTestSkipped('Test DB lacks an active tenant/user');
        }
        TenantContext::setById($this->tenantId);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_monthly_recurring_shift_generates_on_pattern_day_of_month_not_today(): void
    {
        $target = new \DateTime('+5 days');
        $dom = (int) $target->format('j');

        $start = null;
        foreach ([4, 3, 2] as $monthsBack) {
            $candidate = (clone $target)->modify("-{$monthsBack} months");
            if ((int) $candidate->format('j') === $dom) {
                $start = $candidate;
                break;
            }
        }
        if (!$start) {
            $this->markTestSkipped('Could not build a clean monthly anchor');
        }

        $patternId = $this->insertPattern('monthly', $start->format('Y-m-d'), null);
        (new RecurringShiftService())->generateOccurrences($patternId, 14);

        $shifts = DB::table('vol_shifts')
            ->where('recurring_pattern_id', $patternId)
            ->where('tenant_id', $this->tenantId)
            ->pluck('start_time')
            ->all();

        $today = date('Y-m-d');
        $targetDate = $target->format('Y-m-d');

        $this->assertNotEmpty(
            array_filter($shifts, fn ($s) => str_starts_with((string) $s, $targetDate)),
            "Monthly pattern (day {$dom}) should generate an occurrence on {$targetDate}"
        );
        $this->assertEmpty(
            array_filter($shifts, fn ($s) => str_starts_with((string) $s, $today)),
            "Monthly pattern (day {$dom}) must not generate an occurrence today ({$today})"
        );
    }

    public function test_biweekly_recurring_shift_respects_week_parity_of_original_anchor(): void
    {
        $isoDow = (int) date('N');
        $start = date('Y-m-d', strtotime('-7 days'));

        $patternId = $this->insertPattern('biweekly', $start, json_encode([$isoDow]));
        (new RecurringShiftService())->generateOccurrences($patternId, 14);

        $shifts = DB::table('vol_shifts')
            ->where('recurring_pattern_id', $patternId)
            ->where('tenant_id', $this->tenantId)
            ->pluck('start_time')
            ->all();

        $today = date('Y-m-d');
        $plus7 = date('Y-m-d', strtotime('+7 days'));

        $this->assertEmpty(
            array_filter($shifts, fn ($s) => str_starts_with((string) $s, $today)),
            "Biweekly pattern anchored {$start} must not fire today ({$today}) — odd week"
        );
        $this->assertNotEmpty(
            array_filter($shifts, fn ($s) => str_starts_with((string) $s, $plus7)),
            "Biweekly pattern anchored {$start} should fire on {$plus7} (even week)"
        );
    }

    public function test_event_monthly_occurrences_stay_anchored_to_month_end(): void
    {
        $year = (int) date('Y');
        $templateStart = "{$year}-05-31 09:00:00";

        $templateId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'title' => 'Regression monthly template',
            'description' => 'regression',
            'start_time' => $templateStart,
            'end_time' => "{$year}-05-31 10:00:00",
            'is_recurring_template' => 1,
            'created_at' => now(),
        ]);

        DB::table('event_recurrence_rules')->insert([
            'event_id' => $templateId,
            'tenant_id' => $this->tenantId,
            'frequency' => 'monthly',
            'interval_value' => 1,
            'ends_type' => 'after_count',
            'ends_after_count' => 4,
        ]);

        $method = new \ReflectionMethod(\App\Services\EventService::class, 'generateOccurrences');
        $method->setAccessible(true);
        $method->invoke(null, $templateId, []);

        $occurrences = DB::table('events')
            ->where('parent_event_id', $templateId)
            ->orderBy('start_time')
            ->pluck('start_time')
            ->all();

        $this->assertNotEmpty($occurrences, 'Should generate at least one occurrence');
        foreach ($occurrences as $occ) {
            $day = (int) date('j', strtotime((string) $occ));
            $this->assertGreaterThan(
                27,
                $day,
                "Occurrence {$occ} drifted off month-end (day {$day}) — +1 month overflow regression"
            );
        }
        // Time of day must be preserved by the re-anchoring.
        $this->assertSame('09:00:00', date('H:i:s', strtotime((string) $occurrences[0])));
    }

    public function test_leaderboard_season_visible_on_last_day_and_never_duplicated(): void
    {
        DB::table('leaderboard_seasons')->where('tenant_id', $this->tenantId)->delete();

        // createMonthlySeason uses the real clock for month/year, so freeze a
        // time inside the real current month: its last day at 12:00 UTC.
        Carbon::setTestNow(Carbon::parse(date('Y-m-t') . ' 12:00:00', 'UTC'));

        $svc = new LeaderboardSeasonService();
        $first = $svc->getOrCreateCurrentSeason($this->tenantId);
        $second = $svc->getOrCreateCurrentSeason($this->tenantId);

        $rows = DB::table('leaderboard_seasons')->where('tenant_id', $this->tenantId)->get();

        $this->assertNotNull($first, 'Season must be visible on its own final day');
        $this->assertNotNull($second);
        $this->assertCount(1, $rows, 'Repeated getOrCreateCurrentSeason calls must never duplicate the season');
    }

    private function insertPattern(string $frequency, string $startDate, ?string $daysOfWeek): int
    {
        $oppId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->tenantId,
            'title' => 'Regression opportunity',
            'description' => 'regression',
            'is_active' => 1,
            'created_at' => now(),
        ]);

        return (int) DB::table('recurring_shift_patterns')->insertGetId([
            'tenant_id' => $this->tenantId,
            'opportunity_id' => $oppId,
            'created_by' => $this->userId,
            'title' => 'Regression pattern',
            'frequency' => $frequency,
            'days_of_week' => $daysOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'spots_per_shift' => 1,
            'capacity' => 3,
            'start_date' => $startDate,
            'end_date' => null,
            'max_occurrences' => null,
            'occurrences_generated' => 0,
            'is_active' => 1,
        ]);
    }
}
