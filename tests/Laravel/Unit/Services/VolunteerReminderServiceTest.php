<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\VolunteerReminderService;
use Illuminate\Support\Facades\DB;

class VolunteerReminderServiceTest extends TestCase
{
    public function test_sendReminders_returns_zero_when_no_setting(): void
    {
        DB::shouldReceive('table')->with('vol_reminder_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertEquals(0, VolunteerReminderService::sendReminders(2, 1));
    }

    public function test_sendReminders_returns_zero_when_no_shifts(): void
    {
        $setting = (object) ['hours_before' => 24, 'push_enabled' => true, 'email_enabled' => true, 'sms_enabled' => false];
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn($setting);
        DB::shouldReceive('get')->andReturn(collect([]));

        $this->assertEquals(0, VolunteerReminderService::sendReminders(2, 1));
    }

    public function test_scheduleReminder_returns_false_for_invalid_datetime(): void
    {
        $this->assertFalse(VolunteerReminderService::scheduleReminder(2, 1, 'not-a-date'));
    }

    public function test_cancelReminder_returns_false_when_not_found(): void
    {
        DB::shouldReceive('table')->with('vol_reminders_sent')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('delete')->andReturn(0);

        $this->assertFalse(VolunteerReminderService::cancelReminder(2, 999));
    }

    public function test_updateSetting_returns_false_for_invalid_type(): void
    {
        $this->assertFalse(VolunteerReminderService::updateSetting('invalid_type', ['enabled' => true]));
    }

    public function test_updateSetting_accepts_valid_types(): void
    {
        $validTypes = ['pre_shift', 'post_shift_feedback', 'lapsed_volunteer', 'credential_expiry', 'training_expiry'];

        foreach ($validTypes as $type) {
            // Mock DB for each call
            DB::shouldReceive('table')->with('vol_reminder_settings')->andReturnSelf();
            DB::shouldReceive('where')->andReturnSelf();
            DB::shouldReceive('first')->andReturn(null);
            DB::shouldReceive('insert')->andReturn(true);

            $this->assertTrue(VolunteerReminderService::updateSetting($type, ['enabled' => true]));
        }
    }

    public function test_getSettings_returns_defaults_when_no_rows(): void
    {
        DB::shouldReceive('table')->with('vol_reminder_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = VolunteerReminderService::getSettings();

        $this->assertCount(5, $result);
        $this->assertEquals('pre_shift', $result[0]['reminder_type']);
        $this->assertNull($result[0]['id']);
    }
}
