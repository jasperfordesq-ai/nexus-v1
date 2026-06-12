<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EventReminderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression test for the 2026-06-12 reminder retry-storm fix (event side).
 *
 * Recipients on the email_suppression list can never receive mail; previously
 * the event-reminder cron released its delivery claim on the failed send and
 * retried the same dead address on every run (production: 40 attempts for a
 * single event/user pair). A suppressed recipient must now be marked handled
 * in event_reminder_sent — and still receive the in-app bell.
 */
class EventReminderSuppressedRecipientTest extends TestCase
{
    use DatabaseTransactions;

    public function test_suppressed_recipient_marked_handled_and_still_gets_bell(): void
    {
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);

        DB::table('email_suppression')->insertOrIgnore([
            'email' => $user->email,
            'reason' => 'bounce',
            'suppressed_at' => now(),
            'created_at' => now(),
        ]);

        // Event inside the 24h reminder window (24h ± 30min lookahead).
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Suppression Reminder Test Event',
            'description' => 'x',
            'start_time' => now()->addHours(24)->format('Y-m-d H:i:s'),
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $user->id,
            'status' => 'going',
            'created_at' => now(),
        ]);

        (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertTrue(
            DB::table('event_reminder_sent')
                ->where('tenant_id', $this->testTenantId)
                ->where('event_id', $eventId)
                ->where('user_id', $user->id)
                ->where('reminder_type', '24h')
                ->exists(),
            'Suppressed recipient must be marked handled so the cron never retries'
        );

        $this->assertTrue(
            DB::table('notifications')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $user->id)
                ->where('type', 'event_reminder')
                ->exists(),
            'In-app bell must still fire even though the email is skipped'
        );

        $this->assertFalse(
            DB::table('email_log')
                ->where('recipient_email', $user->email)
                ->where('status', 'sent')
                ->exists(),
            'No email may be sent to a suppressed recipient'
        );
    }
}
