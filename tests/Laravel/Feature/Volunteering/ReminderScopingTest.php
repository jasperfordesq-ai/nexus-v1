<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Volunteering;

use App\Core\TenantContext;
use App\Services\VolunteerReminderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * VOL-BE-015: when a reminder sweep is invoked for a single tenant (the runAll /
 * forEachTenant cron path), its collection scans must be scoped to that tenant
 * rather than reading every tenant's settings/shifts and discarding the rest.
 */
class ReminderScopingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pre_shift_reminder_settings_scan_is_scoped_to_the_target_tenant(): void
    {
        TenantContext::setById($this->testTenantId);

        DB::enableQueryLog();
        VolunteerReminderService::sendPreShiftReminders($this->testTenantId);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $settingsScan = collect($log)->first(
            fn ($q) => stripos($q['query'], 'vol_reminder_settings') !== false
                && in_array('pre_shift', $q['bindings'], true)
        );

        $this->assertNotNull($settingsScan, 'the pre_shift settings scan should run');
        $bindings = array_map(fn ($b) => is_numeric($b) ? (int) $b : $b, $settingsScan['bindings']);
        $this->assertContains(
            $this->testTenantId,
            $bindings,
            'the pre_shift settings scan must be scoped to the target tenant, not read every tenant'
        );
    }
}
