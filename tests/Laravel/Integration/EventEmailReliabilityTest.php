<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\EventReminderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class EventEmailReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_event_reminder_claim_releases_after_email_failure_and_allows_retry(): void
    {
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-reminder-retry-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $eventId = $this->createUpcomingEvent();
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app()->instance(EmailDispatchService::class, $this->fakeMailer(false));

        $failed = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(0, $failed);
        $this->assertDatabaseMissing('event_reminder_sent', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'reminder_type' => '24h',
        ]);
        $this->assertDatabaseMissing('event_reminder_delivery_claims', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'reminder_type' => '24h',
        ]);

        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);

        $retried = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(1, $retried);
        $this->assertCount(1, $mailer->calls);
        $this->assertDatabaseHas('event_reminder_sent', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'reminder_type' => '24h',
        ]);
        $this->assertDatabaseHas('event_reminder_delivery_claims', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'reminder_type' => '24h',
            'status' => 'delivered',
        ]);
    }

    public function test_event_reminder_claim_blocks_duplicate_delivery_before_sent_marker(): void
    {
        $this->assertTrue(EventReminderService::claimReminderDelivery($this->testTenantId, 123456, 654321, '24h'));
        $this->assertFalse(EventReminderService::claimReminderDelivery($this->testTenantId, 123456, 654321, '24h'));
        $this->assertSame(0, DB::table('event_reminder_sent')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', 123456)
            ->where('user_id', 654321)
            ->where('reminder_type', '24h')
            ->count());
    }

    private function createUpcomingEvent(): int
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-reminder-organizer-' . uniqid('', true) . '@example.test',
        ]);

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizer->id,
            'title' => 'Reminder Reliability Event',
            'description' => 'A reminder reliability regression test event.',
            'status' => 'active',
            'start_time' => now()->addHours(24),
            'end_time' => now()->addHours(26),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fakeMailer(bool $result): EmailDispatchService
    {
        return new class($result) extends EmailDispatchService {
            /** @var list<array{to:string,subject:string,body:string,options:array<string,mixed>}> */
            public array $calls = [];

            public function __construct(private readonly bool $result)
            {
            }

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = [
                    'to' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'options' => $options,
                ];

                return $this->result;
            }
        };
    }
}
