<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Volunteering;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\RecurringShiftService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * VOL-BE-018: a monthly recurring pattern anchored on day 29/30/31 must still
 * generate a shift in shorter months (clamped to the last valid day) instead of
 * silently skipping them.
 */
class RecurringShiftMonthlyClampTest extends TestCase
{
    use DatabaseTransactions;

    public function test_monthly_pattern_on_day_31_generates_on_last_day_of_short_month(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);

        $orgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $owner->id,
            'name' => 'Recurrence Clamp Org',
            'status' => 'approved',
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oppId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'organization_id' => $orgId,
            'title' => 'Clamp Opportunity',
            'description' => 'x',
            'is_active' => 1,
            'status' => 'open',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Anchor day 31; the window (today .. +100 days) includes September, a
        // 30-day month where day 31 does not exist.
        $patternId = (int) DB::table('recurring_shift_patterns')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $oppId,
            'created_by' => $owner->id,
            'frequency' => 'monthly',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'capacity' => 5,
            'start_date' => '2026-01-31',
            'occurrences_generated' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new RecurringShiftService())->generateOccurrences($patternId, 100);

        // The clamp fires on 30 Sept (min(31, days-in-September)); without it,
        // September would be skipped entirely.
        $this->assertTrue(
            DB::table('vol_shifts')
                ->where('tenant_id', $this->testTenantId)
                ->where('recurring_pattern_id', $patternId)
                ->where('start_time', '2026-09-30 09:00:00')
                ->exists(),
            'expected a shift clamped to 30 September for a day-31 monthly pattern'
        );

        // August (31 days) fires on its 31st, unclamped.
        $this->assertTrue(
            DB::table('vol_shifts')
                ->where('tenant_id', $this->testTenantId)
                ->where('recurring_pattern_id', $patternId)
                ->where('start_time', '2026-08-31 09:00:00')
                ->exists(),
            'expected an unclamped shift on 31 August'
        );
    }
}
