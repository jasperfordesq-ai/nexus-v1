<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\EmailTriggerAuditService;
use App\Services\EventReminderService;
use App\Services\EventReminderChannelDeliveryService;
use App\Services\EventService;
use App\Services\TenantFeatureConfig;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class EventEmailReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // This suite verifies the retained legacy reminder compatibility path.
        // Fresh installations now default to the canonical schedule/outbox.
        Config::set('events.reminders.mode', 'legacy');
    }

    public function test_events_email_opt_out_suppresses_reminder_email_but_keeps_bell(): void
    {
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-reminder-opt-out-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
            'notification_preferences' => ['email_events' => false],
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

        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);

        $sent = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(1, $sent);
        $this->assertCount(0, $mailer->calls);
        $this->assertDatabaseHas('event_reminder_sent', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'reminder_type' => '24h',
        ]);
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $attendee->id,
            'type' => 'event_reminder',
        ]);
    }

    public function test_reminder_uses_event_timezone_and_recipient_locale(): void
    {
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-reminder-timezone-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'de',
        ]);
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.timezone'],
            ['setting_value' => 'Europe/Dublin', 'setting_type' => 'string'],
        );
        $eventId = $this->createUpcomingEvent();
        DB::table('events')->where('id', $eventId)->update([
            'timezone' => 'Pacific/Auckland',
            'timezone_source' => 'explicit',
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $startTime = (string) DB::table('events')->where('id', $eventId)->value('start_time');
        $expectedWhen = \Carbon\Carbon::parse($startTime, 'UTC')
            ->setTimezone('Pacific/Auckland')
            ->locale('de')
            ->isoFormat('LLLL');

        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);

        $sent = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(1, $sent);
        $this->assertCount(1, $mailer->calls);
        $body = html_entity_decode($mailer->calls[0]['body'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString($expectedWhen, $body);
        $this->assertStringContainsString('(Pacific/Auckland)', $body);
        $this->assertStringNotContainsString('(Europe/Dublin)', $body);
    }

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

        $mailer = $this->fakeMailer([false, true]);
        $push = $this->fakePush([true]);
        app()->instance(EmailDispatchService::class, $mailer);
        app()->instance(WebPushService::class, $push);
        $this->registerPushSubscription($attendee->id);

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

        $this->travel(61)->seconds();

        $retried = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(1, $retried);
        $this->assertCount(2, $mailer->calls);
        $this->assertCount(1, $push->calls);
        $this->assertSame(1, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $attendee->id)
            ->where('type', 'event_reminder')
            ->count());
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

    public function test_stale_event_reminder_claim_is_reclaimed_for_retry(): void
    {
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-reminder-stale-claim-' . uniqid('', true) . '@example.test',
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
        DB::table('event_reminder_delivery_claims')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'reminder_type' => '24h',
            'status' => 'claimed',
            'claimed_at' => now()->subHours(2),
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);

        $sent = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(1, $sent);
        $this->assertCount(1, $mailer->calls);
        $this->assertDatabaseHas('event_reminder_delivery_claims', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'reminder_type' => '24h',
            'status' => 'delivered',
        ]);
    }

    public function test_configured_event_email_reminder_is_sent_and_marked(): void
    {
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-configured-reminder-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $eventId = $this->createUpcomingEvent(168);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_reminders')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'remind_before_minutes' => 10080,
            'reminder_type' => 'email',
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);

        $sent = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(1, $sent);
        $this->assertCount(1, $mailer->calls);
        $this->assertSame('event_reminder', $mailer->calls[0]['options']['category']);
        $this->assertSame($this->testTenantId, $mailer->calls[0]['options']['tenant_id']);
        $this->assertEventsCategoryToken((string) $mailer->calls[0]['options']['unsubscribeUrl']);
        $this->assertDatabaseHas('event_reminders', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('event_reminder_sent', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'reminder_type' => '7d',
        ]);
    }

    public function test_configured_email_reminder_opt_out_is_terminal_without_sending(): void
    {
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-configured-opt-out-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
            'notification_preferences' => ['email_events' => false],
        ]);
        $eventId = $this->createUpcomingEvent(168);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_reminders')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'remind_before_minutes' => 10080,
            'reminder_type' => 'email',
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);

        $sent = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(0, $sent);
        $this->assertCount(0, $mailer->calls);
        $this->assertDatabaseHas('event_reminders', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('event_reminder_delivery_claims', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'suppressed',
        ]);
        $this->assertDatabaseHas('event_notification_deliveries', [
            'tenant_id' => $this->testTenantId,
            'recipient_user_id' => $attendee->id,
            'channel' => 'email',
            'status' => 'suppressed',
            'preference_reason' => 'email_events',
        ]);
        $this->assertDatabaseMissing('event_reminder_sent', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'reminder_type' => '7d',
        ]);
    }

    public function test_configured_email_reminder_with_invalid_address_is_suppressed_and_cancelled(): void
    {
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'not-an-email-address',
            'preferred_language' => 'en',
        ]);
        $eventId = $this->createUpcomingEvent(168);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_reminders')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'remind_before_minutes' => 10080,
            'reminder_type' => 'email',
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);

        $sent = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(0, $sent);
        $this->assertCount(0, $mailer->calls);
        $this->assertDatabaseHas('event_reminders', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('event_notification_deliveries', [
            'tenant_id' => $this->testTenantId,
            'recipient_user_id' => $attendee->id,
            'channel' => 'email',
            'status' => 'suppressed',
            'suppression_reason' => 'Recipient has no valid email address',
        ]);
        $this->assertDatabaseMissing('event_reminder_sent', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'reminder_type' => '7d',
        ]);
    }

    public function test_configured_event_email_reminder_stays_pending_after_email_failure(): void
    {
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-configured-reminder-fail-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $eventId = $this->createUpcomingEvent(168);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_reminders')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'remind_before_minutes' => 10080,
            'reminder_type' => 'email',
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app()->instance(EmailDispatchService::class, $this->fakeMailer(false));

        $sent = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(0, $sent);
        $this->assertDatabaseHas('event_reminders', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('event_reminder_sent', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'reminder_type' => '7d',
        ]);
    }

    public function test_configured_both_opt_out_delivers_platform_once_and_records_email_suppression(): void
    {
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-both-opt-out-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
            'notification_preferences' => ['email_events' => false],
        ]);
        $eventId = $this->createUpcomingEvent(168);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_reminders')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'remind_before_minutes' => 10080,
            'reminder_type' => 'both',
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);

        $sent = (new EventReminderService())->sendDueReminders($this->testTenantId);
        $again = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(1, $sent);
        $this->assertSame(0, $again);
        $this->assertCount(0, $mailer->calls);
        $this->assertSame(1, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $attendee->id)
            ->where('type', 'event_reminder')
            ->count());
        $this->assertDatabaseHas('event_notification_deliveries', [
            'tenant_id' => $this->testTenantId,
            'recipient_user_id' => $attendee->id,
            'channel' => 'email',
            'status' => 'suppressed',
            'preference_reason' => 'email_events',
        ]);
        $this->assertDatabaseHas('event_notification_deliveries', [
            'tenant_id' => $this->testTenantId,
            'recipient_user_id' => $attendee->id,
            'channel' => 'in_app',
            'status' => 'delivered',
        ]);
        $this->assertDatabaseHas('event_reminder_delivery_claims', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'suppressed',
            'delivered_at' => null,
        ]);

        $sourceAudit = new \ReflectionMethod(EmailTriggerAuditService::class, 'checkEventReminderSourceHealth');
        $sourceIssues = $sourceAudit->invoke(new EmailTriggerAuditService(), $this->testTenantId, now()->subHour(), 24);
        $claimAudit = new \ReflectionMethod(EmailTriggerAuditService::class, 'checkEventReminderDeliveryClaimHealth');
        $claimIssues = $claimAudit->invoke(new EmailTriggerAuditService(), $this->testTenantId, now()->subHour(), 24);
        $codes = array_column(array_merge($sourceIssues, $claimIssues), 'code');
        $this->assertNotContains('event_reminders_marked_sent_without_email_log', $codes);
        $this->assertNotContains('event_reminder_delivery_claim_delivered_without_email_log', $codes);
    }

    public function test_poison_email_channel_reaches_terminal_without_duplicating_platform_delivery(): void
    {
        Config::set('events.notification_delivery.max_attempts', 1);
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-poison-email-' . uniqid('', true) . '@example.test',
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
        $mailer = $this->fakeMailer(false);
        app()->instance(EmailDispatchService::class, $mailer);

        $sent = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(1, $sent, 'the delivered platform channel completes the reminder');
        $this->assertCount(1, $mailer->calls);
        $this->assertSame(1, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $attendee->id)
            ->where('type', 'event_reminder')
            ->count());
        $this->assertDatabaseHas('event_notification_deliveries', [
            'tenant_id' => $this->testTenantId,
            'recipient_user_id' => $attendee->id,
            'channel' => 'email',
            'status' => 'failed_terminal',
            'attempts' => 1,
        ]);
        $this->assertDatabaseHas('event_notification_deliveries', [
            'tenant_id' => $this->testTenantId,
            'recipient_user_id' => $attendee->id,
            'channel' => 'in_app',
            'status' => 'delivered',
        ]);
    }

    public function test_stale_channel_claim_is_released_using_configured_window(): void
    {
        Config::set('events.notification_delivery.stale_claim_minutes', 1);
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $eventId = $this->createUpcomingEvent();
        $ledger = new EventReminderChannelDeliveryService();
        $deliveries = $ledger->ensureChannels(
            $this->testTenantId,
            $eventId,
            $recipient->id,
            'stale-claim-regression',
            ['email'],
        );
        $first = $ledger->claim($this->testTenantId, (int) $deliveries['email']['id']);
        $this->assertNotNull($first);
        DB::table('event_notification_deliveries')
            ->where('id', $deliveries['email']['id'])
            ->where('tenant_id', $this->testTenantId)
            ->update(['claimed_at' => now()->subMinutes(2)]);

        $second = $ledger->claim($this->testTenantId, (int) $deliveries['email']['id']);

        $this->assertNotNull($second);
        $this->assertNotSame($first['claim_token'], $second['claim_token']);
        $this->assertSame(2, (int) $second['attempts']);
    }

    public function test_disabled_events_feature_pauses_reminders_until_reenabled(): void
    {
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-module-pause-' . uniqid('', true) . '@example.test',
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
        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);
        $this->setEventsFeature(false);

        $paused = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(0, $paused);
        $this->assertCount(0, $mailer->calls);
        $this->assertDatabaseMissing('event_notification_deliveries', [
            'tenant_id' => $this->testTenantId,
            'recipient_user_id' => $attendee->id,
        ]);

        $this->setEventsFeature(true);
        $resumed = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(1, $resumed);
        $this->assertCount(1, $mailer->calls);
    }

    public function test_draft_and_cancelled_events_never_dispatch_reminders(): void
    {
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-inactive-reminder-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $draftEventId = $this->createUpcomingEvent(24, 'draft');
        $cancelledEventId = $this->createUpcomingEvent(168, 'cancelled');

        foreach ([$draftEventId, $cancelledEventId] as $eventId) {
            DB::table('event_rsvps')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'user_id' => $attendee->id,
                'status' => 'going',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('event_reminders')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $cancelledEventId,
            'user_id' => $attendee->id,
            'remind_before_minutes' => 10080,
            'reminder_type' => 'email',
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);

        $sent = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(0, $sent);
        $this->assertCount(0, $mailer->calls);
        $this->assertSame(0, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $attendee->id)
            ->whereIn('link', ["/events/{$draftEventId}", "/events/{$cancelledEventId}"])
            ->count());
        $this->assertSame(0, DB::table('event_notification_deliveries as delivery')
            ->join('event_domain_outbox as outbox', 'outbox.id', '=', 'delivery.outbox_id')
            ->where('delivery.tenant_id', $this->testTenantId)
            ->where('delivery.recipient_user_id', $attendee->id)
            ->whereIn('outbox.event_id', [$draftEventId, $cancelledEventId])
            ->count());
        $this->assertDatabaseMissing('event_reminder_sent', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $draftEventId,
            'user_id' => $attendee->id,
            'reminder_type' => '24h',
        ]);
        $this->assertDatabaseHas('event_reminders', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $cancelledEventId,
            'user_id' => $attendee->id,
            'status' => 'pending',
        ]);
    }

    public function test_configured_event_reminder_is_cancelled_when_rsvp_declined(): void
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-reminder-declined-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
            'status' => 'active',
            'is_approved' => true,
        ]);
        $eventId = $this->createUpcomingEvent(168);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_reminders')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'remind_before_minutes' => 10080,
            'reminder_type' => 'email',
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(
            TenantContext::runForTenant($this->testTenantId, fn (): bool => EventService::rsvp($eventId, $attendee->id, 'declined')),
            json_encode(EventService::getErrors())
        );

        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);
        $sent = (new EventReminderService())->sendDueReminders($this->testTenantId);

        $this->assertSame(0, $sent);
        $this->assertCount(0, $mailer->calls);
        $this->assertDatabaseHas('event_reminders', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'cancelled',
        ]);
    }

    private function createUpcomingEvent(int $hoursFromNow = 24, string $status = 'active'): int
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'event-reminder-organizer-' . uniqid('', true) . '@example.test',
        ]);

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizer->id,
            'title' => 'Reminder Reliability Event',
            'description' => 'A reminder reliability regression test event.',
            'status' => $status,
            'start_time' => now()->addHours($hoursFromNow),
            'end_time' => now()->addHours($hoursFromNow + 2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertEventsCategoryToken(string $url): void
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $decoded = base64_decode(strtr((string) ($query['token'] ?? ''), '-_', '+/'), true);

        $this->assertIsString($decoded);
        $this->assertStringContainsString('.events.', $decoded);
    }

    private function setEventsFeature(bool $enabled): void
    {
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode(array_merge(
                TenantFeatureConfig::FEATURE_DEFAULTS,
                ['events' => $enabled],
            ), JSON_THROW_ON_ERROR)]);
        TenantContext::setById($this->testTenantId);
    }

    private function registerPushSubscription(int $userId): void
    {
        DB::table('push_subscriptions')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'endpoint' => 'https://push.example.test/' . uniqid('', true),
            'p256dh_key' => 'test-key',
            'auth_key' => 'test-auth',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param bool|list<bool> $result */
    private function fakeMailer(bool|array $result): EmailDispatchService
    {
        return new class($result) extends EmailDispatchService {
            /** @var list<array{to:string,subject:string,body:string,options:array<string,mixed>}> */
            public array $calls = [];
            /** @var list<bool> */
            private array $results;

            public function __construct(bool|array $result)
            {
                $this->results = is_array($result) ? array_values($result) : [$result];
            }

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = [
                    'to' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'options' => $options,
                ];

                return (bool) array_shift($this->results);
            }
        };
    }

    /** @param list<bool> $results */
    private function fakePush(array $results): WebPushService
    {
        return new class($results) extends WebPushService {
            /** @var list<array<string,mixed>> */
            public array $calls = [];

            /** @param list<bool> $results */
            public function __construct(private array $results)
            {
            }

            public function sendToUser($userId, $title, $body, $link = null, $type = 'general', $options = []): bool
            {
                $this->calls[] = compact('userId', 'title', 'body', 'link', 'type', 'options');

                return (bool) array_shift($this->results);
            }
        };
    }
}
