<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\User;
use App\Exceptions\EventLifecycleTransitionException;
use App\Enums\EventStaffRole;
use App\Services\EventPublicationWorkflowService;
use App\Services\EventNotificationOutboxProcessor;
use App\Services\EventRoleService;
use App\Services\EventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class EventLifecycleCompatibilityIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('events.notification_delivery.mode', 'direct');
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'events.notification_delivery_mode')
            ->delete();
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_cancel_compatibility_method_requires_reason_and_replays_one_canonical_transition(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $eventId = $this->event((int) $owner->id);

        self::assertFalse(EventService::cancelEvent($eventId, (int) $owner->id, '   '));
        self::assertSame('VALIDATION_REQUIRED_FIELD', EventService::getErrors()[0]['code']);
        self::assertNull(EventService::getLastLifecycleResult());
        $this->assertDatabaseMissing('event_status_history', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
        ]);

        self::assertTrue(EventService::cancelEvent(
            $eventId,
            (int) $owner->id,
            'Unsafe weather',
            'compat-cancel-1',
        ));
        $first = EventService::getLastLifecycleResponse();
        self::assertIsArray($first);
        self::assertSame('cancelled', $first['outcome']);
        self::assertTrue($first['changed']);
        self::assertTrue($first['idempotency_key_supplied']);
        self::assertSame(1, $first['lifecycle_version']);

        self::assertTrue(EventService::cancelEvent(
            $eventId,
            (int) $owner->id,
            'Unsafe weather',
            'compat-cancel-1',
        ));
        $replay = EventService::getLastLifecycleResponse();
        self::assertIsArray($replay);
        self::assertSame('already_cancelled', $replay['outcome']);
        self::assertFalse($replay['changed']);
        self::assertTrue($replay['replayed']);
        self::assertSame(1, DB::table('event_status_history')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->count());
    }

    public function test_delete_compatibility_method_archives_and_never_erases_event_or_attendance(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $eventId = $this->event((int) $owner->id);
        DB::table('event_attendance')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $owner->id,
            'checked_in_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertTrue(EventService::delete(
            $eventId,
            (int) $owner->id,
            'Organizer archive request',
            'compat-archive-1',
        ));
        $result = EventService::getLastLifecycleResponse();
        self::assertIsArray($result);
        self::assertSame('archive', $result['action']);
        self::assertSame('delete', $result['requested_action']);
        self::assertSame('archived', $result['outcome']);
        self::assertTrue($result['archived']);
        self::assertTrue($result['cancelled']);
        self::assertFalse($result['deleted']);
        $this->assertDatabaseHas('events', [
            'tenant_id' => $this->testTenantId,
            'id' => $eventId,
            'publication_status' => 'archived',
            'operational_status' => 'cancelled',
            'lifecycle_version' => 1,
        ]);
        $this->assertDatabaseHas('event_attendance', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $owner->id,
        ]);
    }

    public function test_recurring_cancel_transitions_future_occurrences_and_deduplicates_legacy_fanout(): void
    {
        Config::set('events.notification_delivery.consumer_enabled', true);
        Config::set('events.notification_delivery.channels', ['in_app']);
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'email' => 'series-cancel-' . uniqid('', true) . '@example.test',
        ]);
        $templateId = $this->event((int) $owner->id, ['is_recurring_template' => 1]);
        $occurrenceId = $this->event((int) $owner->id, [
            'parent_event_id' => $templateId,
            'start_time' => now()->addWeeks(2),
            'end_time' => now()->addWeeks(2)->addHours(2),
        ]);
        foreach ([$templateId, $occurrenceId] as $eventId) {
            DB::table('event_rsvps')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'user_id' => $attendee->id,
                'status' => 'going',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('notification_settings')->insert([
            'user_id' => $attendee->id,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => 'daily',
        ]);

        self::assertTrue(EventService::cancelEvent(
            $templateId,
            (int) $owner->id,
            'Series venue closed',
            'series-cancel-1',
        ));
        $result = EventService::getLastLifecycleResponse();
        self::assertIsArray($result);
        self::assertSame(2, $result['series']['target_count']);
        self::assertSame(2, $result['series']['changed_count']);
        self::assertSame(2, $result['cascade']['registrations_cancelled']);
        self::assertSame([(int) $attendee->id], EventService::getLastCancellationRecipientIds());
        $this->assertDatabaseHas('events', [
            'id' => $occurrenceId,
            'operational_status' => 'cancelled',
            'lifecycle_version' => 1,
        ]);
        self::assertSame(2, DB::table('event_status_history')
            ->where('tenant_id', $this->testTenantId)
            ->whereIn('event_id', [$templateId, $occurrenceId])
            ->count());
        self::assertSame(2, DB::table('event_domain_outbox')
            ->where('tenant_id', $this->testTenantId)
            ->whereIn('event_id', [$templateId, $occurrenceId])
            ->count());
        app(EventNotificationOutboxProcessor::class)->processBatch(20, $this->testTenantId);
        self::assertSame(1, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $attendee->id)
            ->where('link', "/events/{$occurrenceId}")
            ->where('type', 'event_cancellation')
            ->count());

        self::assertTrue(EventService::cancelEvent(
            $templateId,
            (int) $owner->id,
            'Series venue closed',
            'series-cancel-1',
        ));
        $replay = EventService::getLastLifecycleResponse();
        self::assertIsArray($replay);
        self::assertSame('already_cancelled', $replay['outcome']);
        self::assertSame(0, $replay['series']['changed_count']);
        self::assertSame(2, $replay['series']['replayed_count']);
        self::assertSame(2, DB::table('event_domain_outbox')
            ->where('tenant_id', $this->testTenantId)
            ->whereIn('event_id', [$templateId, $occurrenceId])
            ->count());
        self::assertSame(1, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $attendee->id)
            ->where('link', "/events/{$occurrenceId}")
            ->where('type', 'event_cancellation')
            ->count());
    }

    public function test_authoritative_large_series_cancel_uses_one_root_fact_with_merged_unique_recipients(): void
    {
        Config::set('events.notification_delivery.mode', 'outbox_authoritative');
        Config::set('events.notification_delivery.consumer_enabled', true);
        Config::set('events.notification_delivery.channels', ['in_app']);
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $templateId = $this->event((int) $owner->id, ['is_recurring_template' => 1]);
        $occurrenceIds = [];
        $attendeeIds = [];
        for ($index = 1; $index <= 21; $index++) {
            $occurrenceId = $this->event((int) $owner->id, [
                'parent_event_id' => $templateId,
                'start_time' => now()->addDays($index),
                'end_time' => now()->addDays($index)->addHour(),
            ]);
            $attendee = User::factory()->forTenant($this->testTenantId)->create([
                'status' => 'active',
                'is_approved' => true,
            ]);
            DB::table('event_rsvps')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $occurrenceId,
                'user_id' => $attendee->id,
                'status' => 'going',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $occurrenceIds[] = $occurrenceId;
            $attendeeIds[] = (int) $attendee->id;
        }

        self::assertTrue(EventService::cancelEvent(
            $templateId,
            (int) $owner->id,
            'Whole series cancellation',
            'authoritative-large-series-cancel',
        ));

        $rootPayload = json_decode((string) DB::table('event_domain_outbox')
            ->where('event_id', $templateId)
            ->where('action', 'event.lifecycle.transitioned')
            ->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        $merged = array_map('intval', $rootPayload['affected_recipient_user_ids']);
        sort($merged);
        sort($attendeeIds);
        self::assertSame($attendeeIds, $merged);
        self::assertSame(22, count($rootPayload['metadata']['series']['affected_event_ids']));
        self::assertSame(21, $rootPayload['metadata']['series']['recipient_count']);

        $childPayloads = DB::table('event_domain_outbox')
            ->whereIn('event_id', $occurrenceIds)
            ->where('action', 'event.lifecycle.transitioned')
            ->pluck('payload');
        self::assertCount(21, $childPayloads);
        foreach ($childPayloads as $payload) {
            $decoded = json_decode((string) $payload, true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($decoded['metadata']['notifications_suppressed']);
        }

        app(EventNotificationOutboxProcessor::class)->processBatch(100, $this->testTenantId);
        foreach ($attendeeIds as $index => $attendeeId) {
            self::assertSame(1, DB::table('notifications')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $attendeeId)
                ->where('type', 'event_cancellation')
                ->where('link', "/events/{$occurrenceIds[$index]}")
                ->count());
        }
    }

    public function test_drifted_series_creates_one_synthetic_root_fact_and_suppresses_child_delivery(): void
    {
        Config::set('events.notification_delivery.mode', 'direct');
        Config::set('events.notification_delivery.consumer_enabled', true);
        Config::set('events.notification_delivery.channels', ['in_app']);
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $templateId = $this->event((int) $owner->id, [
            'is_recurring_template' => 1,
            'status' => 'cancelled',
            'publication_status' => 'published',
            'operational_status' => 'cancelled',
            'lifecycle_version' => 1,
        ]);
        $occurrenceId = $this->event((int) $owner->id, [
            'parent_event_id' => $templateId,
            'start_time' => now()->addWeeks(2),
            'end_time' => now()->addWeeks(2)->addHours(2),
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $occurrenceId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertTrue(EventService::cancelEvent(
            $templateId,
            (int) $owner->id,
            'Repair drifted series state',
            'series-drift-cancel',
        ), json_encode(EventService::getErrors(), JSON_THROW_ON_ERROR));
        $response = EventService::getLastLifecycleResponse();
        self::assertIsArray($response);
        self::assertSame(2, $response['series']['changed_count']);
        self::assertSame(2, (int) DB::table('events')->where('id', $templateId)->value('lifecycle_version'));
        self::assertSame('cancelled', DB::table('events')->where('id', $occurrenceId)->value('operational_status'));

        $rootOutbox = DB::table('event_domain_outbox')
            ->where('event_id', $templateId)
            ->where('action', 'event.lifecycle.transitioned')
            ->first();
        self::assertNotNull($rootOutbox);
        self::assertSame('outbox_authoritative', $rootOutbox->production_mode);
        self::assertSame('pending', $rootOutbox->status);
        $rootPayload = json_decode((string) $rootOutbox->payload, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($rootPayload['metadata']['series']['synthetic_root_revision']);
        self::assertSame('cancel', $rootPayload['metadata']['series']['action']);
        self::assertSame($occurrenceId, (int) $rootPayload['presentation_event_id']);
        self::assertSame(
            $occurrenceId,
            (int) $rootPayload['metadata']['series']['recipient_event_ids'][(string) $attendee->id],
        );

        $childPayload = json_decode((string) DB::table('event_domain_outbox')
            ->where('event_id', $occurrenceId)
            ->where('action', 'event.lifecycle.transitioned')
            ->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($childPayload['metadata']['notifications_suppressed']);

        app(EventNotificationOutboxProcessor::class)->processBatch(20, $this->testTenantId);
        self::assertSame(1, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $attendee->id)
            ->where('type', 'event_cancellation')
            ->where('link', "/events/{$occurrenceId}")
            ->count());

        self::assertTrue(EventService::cancelEvent(
            $templateId,
            (int) $owner->id,
            'Repair drifted series state',
            'series-drift-cancel',
        ));
        $replay = EventService::getLastLifecycleResponse();
        self::assertIsArray($replay);
        self::assertSame(0, $replay['series']['changed_count']);
        self::assertSame(2, DB::table('event_domain_outbox')
            ->whereIn('event_id', [$templateId, $occurrenceId])
            ->where('action', 'event.lifecycle.transitioned')
            ->count());
    }

    public function test_delegated_manager_cancellation_notifies_the_organizer_in_every_delivery_mode(): void
    {
        Config::set('events.notification_delivery.consumer_enabled', true);
        Config::set('events.notification_delivery.channels', ['in_app']);

        foreach (['direct', 'shadow_outbox', 'outbox_authoritative'] as $mode) {
            Config::set('events.notification_delivery.mode', $mode);
            $owner = User::factory()->forTenant($this->testTenantId)->create([
                'status' => 'active',
                'is_approved' => true,
            ]);
            $manager = User::factory()->forTenant($this->testTenantId)->create([
                'status' => 'active',
                'is_approved' => true,
            ]);
            $eventId = $this->event((int) $owner->id);
            app(EventRoleService::class)->grant(
                $eventId,
                (int) $manager->id,
                EventStaffRole::CoOrganizer,
                $owner,
            );

            self::assertTrue(EventService::cancelEvent(
                $eventId,
                (int) $manager->id,
                "Delegated cancellation {$mode}",
                "delegated-cancel-{$mode}",
            ));
            if ($mode === 'outbox_authoritative') {
                app(EventNotificationOutboxProcessor::class)->processBatch(20, $this->testTenantId);
            }

            self::assertSame(1, DB::table('notifications')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $owner->id)
                ->where('link', "/events/{$eventId}")
                ->whereIn('type', ['event', 'event_cancellation'])
                ->count());
        }
    }

    public function test_compatibility_sources_have_no_physical_event_delete_or_legacy_cancel_write(): void
    {
        $service = file_get_contents(base_path('app/Services/EventService.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/EventsController.php'));
        self::assertIsString($service);
        self::assertIsString($controller);

        foreach ([$service, $controller] as $source) {
            self::assertStringNotContainsString('DELETE FROM events', $source);
            self::assertStringNotContainsString('DELETE FROM event_recurrence_rules', $source);
            self::assertStringNotContainsString("UPDATE events SET status = 'cancelled'", $source);
        }
        self::assertStringContainsString('EventLifecycleService::class', $service);
    }

    public function test_publication_from_child_resolves_template_and_transitions_the_whole_series(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'moderation.enabled'],
            ['setting_value' => '1', 'updated_at' => now()],
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'moderation.require_event'],
            ['setting_value' => '1', 'updated_at' => now()],
        );
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'status' => 'active',
        ]);
        $templateId = $this->event((int) $owner->id, [
            'status' => 'draft',
            'publication_status' => 'draft',
            'is_recurring_template' => 1,
        ]);
        $occurrenceId = $this->event((int) $owner->id, [
            'status' => 'draft',
            'publication_status' => 'draft',
            'parent_event_id' => $templateId,
        ]);
        $workflow = app(EventPublicationWorkflowService::class);

        $submitted = $workflow->submit($occurrenceId, $owner);
        self::assertSame($occurrenceId, (int) $submitted['result']->event->id);
        self::assertSame($templateId, $submitted['series']['root_event_id']);
        self::assertSame(2, $submitted['series']['changed_count']);
        self::assertSame(
            ['pending_review', 'pending_review'],
            DB::table('events')->whereIn('id', [$templateId, $occurrenceId])->orderBy('id')->pluck('publication_status')->all(),
        );
        self::assertDatabaseHas('content_moderation_queue', [
            'tenant_id' => $this->testTenantId,
            'content_type' => 'event',
            'content_id' => $templateId,
            'author_id' => $owner->id,
            'status' => 'pending',
        ]);

        $replayed = $workflow->submit($occurrenceId, $owner);
        self::assertSame(0, $replayed['series']['changed_count']);
        self::assertSame(1, DB::table('content_moderation_queue')
            ->where('tenant_id', $this->testTenantId)
            ->where('content_type', 'event')
            ->where('content_id', $templateId)
            ->count());

        $approved = $workflow->approve($occurrenceId, $admin, 'moderation.approved');
        self::assertSame(2, $approved['series']['changed_count']);
        self::assertSame(
            ['published', 'published'],
            DB::table('events')->whereIn('id', [$templateId, $occurrenceId])->orderBy('id')->pluck('publication_status')->all(),
        );
        self::assertSame(4, DB::table('event_status_history')
            ->whereIn('event_id', [$templateId, $occurrenceId])
            ->count());
        self::assertSame(4, DB::table('event_domain_outbox')
            ->whereIn('event_id', [$templateId, $occurrenceId])
            ->where('action', 'event.lifecycle.transitioned')
            ->count());
        self::assertDatabaseHas('content_moderation_queue', [
            'tenant_id' => $this->testTenantId,
            'content_type' => 'event',
            'content_id' => $templateId,
            'status' => 'approved',
            'reviewer_id' => $admin->id,
        ]);

        $childPayload = json_decode((string) DB::table('event_domain_outbox')
            ->where('event_id', $occurrenceId)
            ->orderByDesc('id')
            ->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($childPayload['metadata']['notifications_suppressed']);
        self::assertSame($templateId, $childPayload['metadata']['series']['root_event_id']);
    }

    public function test_publication_repairs_child_drift_through_one_synthetic_root_fact(): void
    {
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->whereIn('setting_key', ['moderation.enabled', 'moderation.require_event'])
            ->delete();
        Config::set('events.notification_delivery.mode', 'direct');
        Config::set('events.notification_delivery.consumer_enabled', true);
        Config::set('events.notification_delivery.channels', ['in_app']);
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $templateId = $this->event((int) $owner->id, [
            'is_recurring_template' => 1,
            'status' => 'active',
            'publication_status' => 'published',
            'lifecycle_version' => 0,
        ]);
        $occurrenceId = $this->event((int) $owner->id, [
            'parent_event_id' => $templateId,
            'status' => 'draft',
            'publication_status' => 'draft',
            'lifecycle_version' => 0,
        ]);
        $workflow = app(EventPublicationWorkflowService::class);

        $operation = $workflow->publish($occurrenceId, $owner);

        self::assertSame(2, $operation['series']['changed_count']);
        self::assertSame(1, (int) DB::table('events')->where('id', $templateId)->value('lifecycle_version'));
        self::assertSame('published', DB::table('events')->where('id', $occurrenceId)->value('publication_status'));
        $rootOutbox = DB::table('event_domain_outbox')
            ->where('event_id', $templateId)
            ->where('action', 'event.lifecycle.transitioned')
            ->first();
        self::assertNotNull($rootOutbox);
        self::assertSame('outbox_authoritative', $rootOutbox->production_mode);
        $rootPayload = json_decode((string) $rootOutbox->payload, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($rootPayload['metadata']['series']['synthetic_root_revision']);
        self::assertSame('publish', $rootPayload['metadata']['series']['action']);
        self::assertSame($occurrenceId, (int) $rootPayload['presentation_event_id']);
        $childPayload = json_decode((string) DB::table('event_domain_outbox')
            ->where('event_id', $occurrenceId)
            ->where('action', 'event.lifecycle.transitioned')
            ->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($childPayload['metadata']['notifications_suppressed']);

        app(EventNotificationOutboxProcessor::class)->processBatch(20, $this->testTenantId);
        self::assertSame(1, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $owner->id)
            ->where('type', 'event_lifecycle')
            ->where('link', "/events/{$occurrenceId}")
            ->count());

        $replay = $workflow->publish($occurrenceId, $owner);
        self::assertSame(0, $replay['series']['changed_count']);
        self::assertSame(2, DB::table('event_domain_outbox')
            ->whereIn('event_id', [$templateId, $occurrenceId])
            ->where('action', 'event.lifecycle.transitioned')
            ->count());
    }

    public function test_moderation_required_blocks_direct_owner_publish(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'moderation.enabled'],
            ['setting_value' => '1', 'updated_at' => now()],
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'moderation.require_event'],
            ['setting_value' => '1', 'updated_at' => now()],
        );
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $eventId = $this->event((int) $owner->id, [
            'status' => 'draft',
            'publication_status' => 'draft',
        ]);

        try {
            app(EventPublicationWorkflowService::class)->publish($eventId, $owner);
            self::fail('Moderated Event was directly published by its owner.');
        } catch (EventLifecycleTransitionException $exception) {
            self::assertSame('event_publication_review_required', $exception->reasonCode);
        }

        self::assertSame('draft', DB::table('events')->where('id', $eventId)->value('publication_status'));
        self::assertSame(0, DB::table('event_status_history')->where('event_id', $eventId)->count());
    }

    public function test_pending_review_rejects_material_edits_and_the_permission_projection_is_truthful(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'moderation.enabled'],
            ['setting_value' => '1', 'updated_at' => now()],
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'moderation.require_event'],
            ['setting_value' => '1', 'updated_at' => now()],
        );
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create(['status' => 'active']);
        $eventId = $this->event((int) $owner->id, [
            'title' => 'Reviewed title remains authoritative',
            'status' => 'draft',
            'publication_status' => 'draft',
        ]);
        $workflow = app(EventPublicationWorkflowService::class);
        $workflow->submit($eventId, $owner);

        self::assertFalse(EventService::update($eventId, (int) $owner->id, [
            'title' => 'Unreviewed replacement title',
        ]));
        self::assertSame('EVENT_REVIEW_PENDING', EventService::getErrors()[0]['code']);
        self::assertSame('Reviewed title remains authoritative', DB::table('events')->where('id', $eventId)->value('title'));

        Sanctum::actingAs($owner, ['*']);
        $this->apiGet("/v2/events/{$eventId}", ['X-Events-Contract' => '2'])
            ->assertOk()
            ->assertJsonPath('data.permissions.edit', false);

        $workflow->approveModerationDecision($eventId, $admin);
        self::assertSame('published', DB::table('events')->where('id', $eventId)->value('publication_status'));
        self::assertSame('Reviewed title remains authoritative', DB::table('events')->where('id', $eventId)->value('title'));
    }

    public function test_every_real_publication_uses_the_authoritative_audience_in_direct_and_shadow_modes(): void
    {
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->whereIn('setting_key', ['moderation.enabled', 'moderation.require_event'])
            ->delete();

        foreach (['direct', 'shadow_outbox'] as $mode) {
            Config::set('events.notification_delivery.mode', $mode);
            $owner = User::factory()->forTenant($this->testTenantId)->create([
                'status' => 'active',
                'is_approved' => true,
            ]);
            $eventId = $this->event((int) $owner->id, [
                'status' => 'draft',
                'publication_status' => 'draft',
            ]);

            $operation = app(EventPublicationWorkflowService::class)->publish($eventId, $owner);

            self::assertSame('outbox_authoritative', $operation['result']->deliveryMode);
            $this->assertDatabaseHas('event_domain_outbox', [
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'action' => 'event.lifecycle.transitioned',
                'production_mode' => 'outbox_authoritative',
                'status' => 'pending',
            ]);
        }
    }

    public function test_operational_roles_with_stale_admin_flags_cannot_publish_another_members_event(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $workflow = app(EventPublicationWorkflowService::class);

        foreach (['broker', 'coordinator'] as $role) {
            $actor = User::factory()->forTenant($this->testTenantId)->create([
                'role' => $role,
                'is_admin' => true,
                'is_super_admin' => true,
                'is_tenant_super_admin' => true,
                'status' => 'active',
            ]);
            $eventId = $this->event((int) $owner->id, [
                'status' => 'draft',
                'publication_status' => 'draft',
            ]);

            try {
                $workflow->publish($eventId, $actor);
                self::fail("{$role} with stale flags published another member's Event.");
            } catch (EventLifecycleTransitionException $exception) {
                self::assertSame('event_lifecycle_authorization_denied', $exception->reasonCode);
            }
            self::assertSame('draft', DB::table('events')->where('id', $eventId)->value('publication_status'));
        }
    }

    public function test_admin_downgrade_after_precheck_is_revalidated_under_the_root_decision_lock(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'status' => 'active',
        ]);
        $eventId = $this->event((int) $admin->id, [
            'status' => 'draft',
            'publication_status' => 'pending_review',
        ]);
        DB::table('content_moderation_queue')->insert([
            'tenant_id' => $this->testTenantId,
            'content_type' => 'event',
            'content_id' => $eventId,
            'author_id' => $admin->id,
            'title' => 'Serialized admin downgrade fixture',
            'status' => 'pending',
            'auto_flagged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $downgraded = false;
        DB::listen(function ($query) use (&$downgraded, $admin): void {
            $sql = strtolower((string) $query->sql);
            if ($downgraded
                || ! str_contains($sql, 'from `events`')
                || ! str_contains($sql, 'for update')) {
                return;
            }
            $downgraded = true;
            DB::table('users')->where('id', $admin->id)->update([
                'role' => 'broker',
                'is_admin' => 1,
                'is_super_admin' => 1,
                'is_tenant_super_admin' => 1,
            ]);
        });

        try {
            app(EventPublicationWorkflowService::class)
                ->approveModerationDecision($eventId, $admin);
            self::fail('An admin downgrade racing the serialized decision was ignored.');
        } catch (EventLifecycleTransitionException $exception) {
            self::assertSame('event_lifecycle_authorization_denied', $exception->reasonCode);
        }

        self::assertTrue($downgraded);
        self::assertSame('pending_review', DB::table('events')->where('id', $eventId)->value('publication_status'));
        self::assertSame('pending', DB::table('content_moderation_queue')
            ->where('content_id', $eventId)
            ->value('status'));
    }

    public function test_moderation_submission_upgrades_direct_and_shadow_modes_to_durable_delivery(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'moderation.enabled'],
            ['setting_value' => '1', 'updated_at' => now()],
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'moderation.require_event'],
            ['setting_value' => '1', 'updated_at' => now()],
        );
        Config::set('events.notification_delivery.consumer_enabled', true);
        Config::set('events.notification_delivery.channels', ['in_app']);

        foreach (['direct', 'shadow_outbox'] as $mode) {
            Config::set('events.notification_delivery.mode', $mode);
            $owner = User::factory()->forTenant($this->testTenantId)->create([
                'status' => 'active',
                'is_approved' => true,
            ]);
            $admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
                'status' => 'active',
            ]);
            $priorParticipant = User::factory()->forTenant($this->testTenantId)->create([
                'status' => 'active',
                'is_approved' => true,
            ]);
            $eventId = $this->event((int) $owner->id, [
                'title' => "Moderation delivery {$mode}",
                'status' => 'draft',
                'publication_status' => 'draft',
            ]);
            DB::table('event_rsvps')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'user_id' => $priorParticipant->id,
                'status' => 'going',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $workflow = app(EventPublicationWorkflowService::class);
            $workflow->submit($eventId, $owner);

            $this->assertDatabaseHas('event_domain_outbox', [
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'action' => 'event.lifecycle.transitioned',
                'production_mode' => 'outbox_authoritative',
                'status' => 'pending',
            ]);
            $processor = app(EventNotificationOutboxProcessor::class);
            $processor->processBatch(20, $this->testTenantId);

            $workflow->reject($eventId, $admin, 'More detail is required.');
            $processor->processBatch(20, $this->testTenantId);
            $workflow->submit($eventId, $owner);
            $processor->processBatch(20, $this->testTenantId);
            $workflow->approve($eventId, $admin);
            $processor->processBatch(20, $this->testTenantId);
            $replay = $processor->processBatch(20, $this->testTenantId);

            self::assertSame(4, DB::table('event_domain_outbox')
                ->where('tenant_id', $this->testTenantId)
                ->where('event_id', $eventId)
                ->where('action', 'event.lifecycle.transitioned')
                ->where('production_mode', 'outbox_authoritative')
                ->count());
            self::assertSame(3, DB::table('notifications')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $owner->id)
                ->where('type', 'event_moderation')
                ->count());
            self::assertSame(1, DB::table('notifications')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $owner->id)
                ->where('type', 'event_lifecycle')
                ->count());
            self::assertSame(1, DB::table('notifications')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $owner->id)
                ->where('type', 'event_moderation')
                ->where('message', 'like', '%More detail is required.%')
                ->where('link', "/events/{$eventId}")
                ->count());
            self::assertSame(0, DB::table('notifications')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $priorParticipant->id)
                ->count());
            self::assertSame(0, $replay['claimed']);
        }
    }

    public function test_publish_closes_a_stale_pending_moderation_queue_row(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $eventId = $this->event((int) $owner->id, [
            'status' => 'draft',
            'publication_status' => 'pending_review',
        ]);
        DB::table('content_moderation_queue')->insert([
            'tenant_id' => $this->testTenantId,
            'content_type' => 'event',
            'content_id' => $eventId,
            'author_id' => $owner->id,
            'title' => 'Stale review row',
            'status' => 'pending',
            'auto_flagged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(EventPublicationWorkflowService::class)->publish($eventId, $owner);

        self::assertSame('published', DB::table('events')->where('id', $eventId)->value('publication_status'));
        self::assertDatabaseHas('content_moderation_queue', [
            'tenant_id' => $this->testTenantId,
            'content_type' => 'event',
            'content_id' => $eventId,
            'status' => 'approved',
            'reviewer_id' => $owner->id,
        ]);
    }

    private function event(int $ownerId, array $overrides = []): int
    {
        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'title' => 'Lifecycle compatibility event',
            'description' => 'Canonical lifecycle compatibility coverage.',
            'location' => 'Test venue',
            'start_time' => now()->addWeek(),
            'end_time' => now()->addWeek()->addHours(2),
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
