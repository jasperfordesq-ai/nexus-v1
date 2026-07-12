<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use App\Services\EventNotificationOutboxProcessor;
use App\Services\EventRecurrenceRevisionService;
use App\Support\Events\EventRecurrenceRevisionSchemaGuard;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class EventRecurrenceRevisionIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('events.recurrence.engine_v2_enabled', true);
        config()->set('events.recurrence.revisions.preview_ttl_seconds', 60);
        config()->set('events.recurrence.revisions.max_affected_occurrences', 1000);
        config()->set('events.notification_delivery.mode', 'outbox_authoritative');
    }

    public function test_selected_boundary_preview_is_non_mutating_and_commit_excludes_earlier_upcoming_rows(): void
    {
        $organizer = $this->activeUser();
        $series = $this->createSeries($organizer, '2027-06-15 09:00:00', 4);
        $selectedId = $series['occurrences'][1];
        $before = DB::table('events')
            ->where('parent_event_id', $series['root'])
            ->orderBy('recurrence_id')
            ->get(['id', 'title', 'start_time', 'parent_event_id', 'recurrence_id']);

        $preview = $this->preview($selectedId, [
            'title' => 'Effective title',
            'local_start_time' => '11:30',
        ])->assertOk();

        self::assertSame(
            array_slice($series['occurrences'], 1),
            $preview->json('data.impact.affected_event_ids'),
        );
        self::assertSame(3, $preview->json('data.impact.affected_count'));
        self::assertSame(0, DB::table('event_recurrence_revisions')->count());
        self::assertEquals($before, DB::table('events')
            ->where('parent_event_id', $series['root'])
            ->orderBy('recurrence_id')
            ->get(['id', 'title', 'start_time', 'parent_event_id', 'recurrence_id']));

        $commit = $this->commit(
            $selectedId,
            ['title' => 'Effective title', 'local_start_time' => '11:30'],
            (string) $preview->json('data.preview_token'),
            'selected-boundary-commit',
        )->assertCreated();
        self::assertSame(3, $commit->json('data.changed_count'));
        self::assertSame('V2 revision fixture', DB::table('events')
            ->where('id', $series['occurrences'][0])->value('title'));
        self::assertSame('Effective title', DB::table('events')
            ->where('id', $selectedId)->value('title'));
        self::assertSame($series['root'], (int) DB::table('events')
            ->where('id', $selectedId)->value('parent_event_id'));
        self::assertSame((string) $before[1]->recurrence_id, DB::table('events')
            ->where('id', $selectedId)->value('recurrence_id'));
    }

    public function test_preview_token_rejects_tamper_patch_actor_expiry_and_stale_materialized_state(): void
    {
        $organizer = $this->activeUser();
        $other = $this->activeUser();
        $series = $this->createSeries($organizer, '2027-07-01 09:00:00', 2);
        $selectedId = $series['occurrences'][0];
        $preview = $this->preview($selectedId, ['location' => 'New venue'])->assertOk();
        $token = (string) $preview->json('data.preview_token');

        $this->commit($selectedId, ['location' => 'New venue'], $token . 'x', 'tamper')
            ->assertConflict()
            ->assertJsonPath('errors.0.code', 'EVENT_RECURRENCE_REVISION_PREVIEW_INVALID');
        $this->commit($selectedId, ['location' => 'Different venue'], $token, 'patch-mismatch')
            ->assertConflict()
            ->assertJsonPath('errors.0.code', 'EVENT_RECURRENCE_REVISION_PREVIEW_INVALID');

        Sanctum::actingAs($other, ['*']);
        $this->commit($selectedId, ['location' => 'New venue'], $token, 'actor-mismatch')
            ->assertConflict()
            ->assertJsonPath('errors.0.code', 'EVENT_RECURRENCE_REVISION_PREVIEW_INVALID');
        Sanctum::actingAs($organizer, ['*']);

        Carbon::setTestNow(now()->addSeconds(61));
        $this->commit($selectedId, ['location' => 'New venue'], $token, 'expired')
            ->assertConflict()
            ->assertJsonPath('errors.0.code', 'EVENT_RECURRENCE_REVISION_PREVIEW_EXPIRED');
        Carbon::setTestNow();

        $fresh = $this->preview($selectedId, ['location' => 'New venue'])->assertOk();
        DB::table('events')->where('id', $selectedId)->increment('calendar_sequence');
        $this->commit(
            $selectedId,
            ['location' => 'New venue'],
            (string) $fresh->json('data.preview_token'),
            'stale-checksum',
        )->assertConflict()
            ->assertJsonPath('errors.0.code', 'EVENT_RECURRENCE_REVISION_CONFLICT');
    }

    public function test_commit_is_idempotent_after_state_moves_and_key_mismatch_conflicts(): void
    {
        $organizer = $this->activeUser();
        $publishedAttendee = $this->activeUser();
        $draftAttendee = $this->activeUser();
        $series = $this->createSeries($organizer, '2027-08-01 09:00:00', 2);
        $selectedId = $series['occurrences'][0];
        DB::table('events')->where('id', $series['root'])->update([
            'status' => 'active',
            'publication_status' => 'published',
        ]);
        DB::table('events')->where('id', $selectedId)->update([
            'status' => 'active',
            'publication_status' => 'published',
        ]);
        foreach ([
            [$selectedId, (int) $publishedAttendee->id],
            [$series['occurrences'][1], (int) $draftAttendee->id],
        ] as [$eventId, $userId]) {
            DB::table('event_rsvps')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'user_id' => $userId,
                'status' => 'going',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $preview = $this->preview($selectedId, ['location' => 'Replay venue'])->assertOk();
        $token = (string) $preview->json('data.preview_token');

        $created = $this->commit(
            $selectedId,
            ['location' => 'Replay venue'],
            $token,
            'stable-key',
        )->assertCreated();
        DB::table('events')->where('id', $selectedId)->increment('calendar_sequence');
        $replay = $this->commit(
            $selectedId,
            ['location' => 'Replay venue'],
            $token,
            'stable-key',
        )->assertOk();
        self::assertTrue($replay->json('data.idempotent_replay'));
        self::assertSame($created->json('data.revision_id'), $replay->json('data.revision_id'));
        self::assertSame(1, $created->json('data.notification_recipient_count'));
        self::assertSame(
            $created->json('data.notification_recipient_count'),
            $replay->json('data.notification_recipient_count'),
        );
        self::assertSame(1, DB::table('event_recurrence_revisions')
            ->where('root_event_id', $series['root'])->count());

        $otherPreview = $this->preview($selectedId, ['location' => 'Other venue'])->assertOk();
        $this->commit(
            $selectedId,
            ['location' => 'Other venue'],
            (string) $otherPreview->json('data.preview_token'),
            'stable-key',
        )->assertConflict()
            ->assertJsonPath('errors.0.code', 'EVENT_RECURRENCE_REVISION_CONFLICT');
    }

    public function test_customized_overrides_skip_rows_prevent_false_notifications_and_persist_future_blueprint(): void
    {
        $organizer = $this->activeUser();
        $attendee = $this->activeUser();
        $series = $this->createSeries($organizer, '2027-09-01 09:00:00', 2);
        DB::table('events')
            ->where(static fn ($query) => $query
                ->where('id', $series['root'])
                ->orWhere('parent_event_id', $series['root']))
            ->update(['status' => 'active', 'publication_status' => 'published']);
        foreach ($series['occurrences'] as $eventId) {
            DB::table('events')->where('id', $eventId)->update([
                'is_recurrence_exception' => 1,
                'recurrence_override_fields' => json_encode(['title'], JSON_THROW_ON_ERROR),
                'recurrence_override_version' => 1,
                'recurrence_override_updated_at' => now(),
                'recurrence_override_updated_by' => (int) $organizer->id,
            ]);
            DB::table('event_rsvps')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'user_id' => $attendee->id,
                'status' => 'going',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $preview = $this->preview($series['occurrences'][0], ['title' => 'Future inherited title'])
            ->assertOk();
        self::assertSame(0, $preview->json('data.impact.changed_count'));
        self::assertCount(2, $preview->json('data.impact.customized_exception_conflicts'));
        $commit = $this->commit(
            $series['occurrences'][0],
            ['title' => 'Future inherited title'],
            (string) $preview->json('data.preview_token'),
            'all-overridden',
        )->assertCreated();
        self::assertSame(0, $commit->json('data.changed_count'));
        self::assertNull($commit->json('data.notification_outbox_id'));
        self::assertSame(0, DB::table('event_domain_outbox')
            ->where('event_id', $series['root'])->where('action', 'event.updated')->count());
        self::assertSame('V2 revision fixture', DB::table('events')
            ->where('id', $series['occurrences'][0])->value('title'));

        $future = app(EventRecurrenceRevisionService::class)->effectiveBlueprint(
            $this->testTenantId,
            $series['root'],
            '20270915T080000Z',
            '2027-09-15 08:00:00',
            [
                'title' => 'V2 revision fixture',
                'description' => 'Fixture',
                'location' => 'Hall',
                'start_time' => '2027-09-15 08:00:00',
                'end_time' => '2027-09-15 09:00:00',
                'timezone' => 'Europe/Dublin',
            ],
        );
        self::assertSame('Future inherited title', $future['title']);
    }

    public function test_capacity_and_rule_shape_conflicts_are_redacted_and_fail_closed(): void
    {
        $organizer = $this->activeUser();
        $attendees = [$this->activeUser(), $this->activeUser()];
        $series = $this->createSeries($organizer, '2027-10-01 09:00:00', 2);
        $selectedId = $series['occurrences'][0];
        foreach ($attendees as $attendee) {
            DB::table('event_rsvps')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $selectedId,
                'user_id' => $attendee->id,
                'status' => 'going',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $capacity = $this->preview($selectedId, ['max_attendees' => 1])->assertOk();
        self::assertFalse($capacity->json('data.can_commit'));
        self::assertSame(
            'capacity_below_committed_occupancy',
            $capacity->json('data.impact.blocking_conflicts.0.code'),
        );
        self::assertStringNotContainsString(
            (string) $attendees[0]->email,
            $capacity->getContent(),
        );

        $rule = $this->preview($selectedId, [
            'recurrence_rrule' => 'FREQ=WEEKLY;COUNT=4',
        ])->assertOk();
        self::assertFalse($rule->json('data.can_commit'));
        self::assertSame(
            'schedule_mapping_resolution_required',
            $rule->json('data.impact.blocking_conflicts.0.code'),
        );
        $this->commit(
            $selectedId,
            ['recurrence_rrule' => 'FREQ=WEEKLY;COUNT=4'],
            (string) $rule->json('data.preview_token'),
            'ambiguous-rule',
        )->assertConflict();
    }

    public function test_dst_gap_and_fold_are_reported_as_blocking_preview_conflicts(): void
    {
        $organizer = $this->activeUser();
        $gap = $this->createSeries($organizer, '2027-03-28 09:00:00', 1, 'UTC');
        $gapPreview = $this->preview($gap['occurrences'][0], [
            'timezone' => 'Europe/Dublin',
            'local_start_time' => '01:30',
        ])->assertOk();
        self::assertFalse($gapPreview->json('data.can_commit'));
        self::assertSame(
            'wall_time_nonexistent',
            $gapPreview->json('data.impact.blocking_conflicts.0.code'),
        );

        $fold = $this->createSeries($organizer, '2027-10-31 09:00:00', 1, 'UTC');
        $foldPreview = $this->preview($fold['occurrences'][0], [
            'timezone' => 'Europe/Dublin',
            'local_start_time' => '01:30',
        ])->assertOk();
        self::assertFalse($foldPreview->json('data.can_commit'));
        self::assertSame(
            'wall_time_ambiguous',
            $foldPreview->json('data.impact.blocking_conflicts.0.code'),
        );
    }

    public function test_only_one_revision_wins_from_two_preview_tokens(): void
    {
        $organizer = $this->activeUser();
        $series = $this->createSeries($organizer, '2027-11-01 09:00:00', 2);
        $selectedId = $series['occurrences'][0];
        $previewA = $this->preview($selectedId, ['location' => 'Winning venue'])->assertOk();
        $previewB = $this->preview($selectedId, ['location' => 'Competing venue'])->assertOk();

        $this->commit(
            $selectedId,
            ['location' => 'Winning venue'],
            (string) $previewA->json('data.preview_token'),
            'concurrent-a',
        )->assertCreated();
        $this->commit(
            $selectedId,
            ['location' => 'Competing venue'],
            (string) $previewB->json('data.preview_token'),
            'concurrent-b',
        )->assertConflict();
        self::assertSame(1, DB::table('event_recurrence_revisions')
            ->where('root_event_id', $series['root'])->count());
    }

    public function test_rollout_flag_blocks_new_work_but_preserves_safe_replay(): void
    {
        $organizer = $this->activeUser();
        $series = $this->createSeries($organizer, '2028-01-01 09:00:00', 2);
        $selectedId = $series['occurrences'][0];
        $preview = $this->preview($selectedId, ['location' => 'Canary venue'])->assertOk();
        $token = (string) $preview->json('data.preview_token');
        $this->commit($selectedId, ['location' => 'Canary venue'], $token, 'flag-replay')
            ->assertCreated();

        config()->set('events.recurrence.engine_v2_enabled', false);
        $this->preview($selectedId, ['location' => 'Blocked venue'])
            ->assertServiceUnavailable()
            ->assertJsonPath('errors.0.code', 'EVENT_RECURRENCE_REVISION_UNAVAILABLE');
        $replay = $this->commit(
            $selectedId,
            ['location' => 'Canary venue'],
            $token,
            'flag-replay',
        )->assertOk();
        self::assertTrue($replay->json('data.idempotent_replay'));
    }

    public function test_replay_survives_retirement_of_a_referenced_category(): void
    {
        $organizer = $this->activeUser();
        $series = $this->createSeries($organizer, '2028-01-15 09:00:00', 1);
        $categoryId = (int) DB::table('categories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Revision replay category',
            'slug' => 'revision-replay-category',
            'type' => 'event',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $patch = ['category_id' => $categoryId];
        $preview = $this->preview($series['occurrences'][0], $patch)->assertOk();
        $token = (string) $preview->json('data.preview_token');
        $created = $this->commit(
            $series['occurrences'][0],
            $patch,
            $token,
            'category-retirement-replay',
        )->assertCreated();

        DB::table('categories')->where('id', $categoryId)->update(['is_active' => 0]);
        config()->set('events.recurrence.engine_v2_enabled', false);

        $replay = $this->commit(
            $series['occurrences'][0],
            $patch,
            $token,
            'category-retirement-replay',
        )->assertOk();
        self::assertTrue($replay->json('data.idempotent_replay'));
        self::assertSame($created->json('data.revision_id'), $replay->json('data.revision_id'));
    }

    public function test_historical_rows_do_not_consume_the_future_boundary_cap(): void
    {
        $organizer = $this->activeUser();
        $series = $this->createSeries($organizer, '2028-02-01 09:00:00', 5);
        config()->set('events.recurrence.revisions.max_affected_occurrences', 2);

        $this->preview($series['occurrences'][3], ['location' => 'Bounded venue'])
            ->assertOk()
            ->assertJsonPath('data.impact.affected_count', 2);
        $this->preview($series['occurrences'][0], ['location' => 'Too broad'])
            ->assertStatus(413)
            ->assertJsonPath('errors.0.code', 'EVENT_RECURRENCE_REVISION_LIMIT_EXCEEDED');
    }

    public function test_maintained_blueprint_fields_are_strict_and_unsupported_associations_are_explicit(): void
    {
        $organizer = $this->activeUser();
        $series = $this->createSeries($organizer, '2028-03-01 09:00:00', 1, 'UTC');
        $eventId = $series['occurrences'][0];
        $patch = [
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'video_url' => 'https://media.example.test/event',
            'all_day' => true,
            'local_start_time' => '00:00',
            'local_end_time' => '00:00',
            'accessibility_step_free' => true,
            'accessibility_toilet' => true,
            'accessibility_parking_details' => 'Reserved bays beside the entrance.',
            'accessibility_notes' => 'Contact the organiser for adjustments.',
        ];
        $preview = $this->preview($eventId, $patch)->assertOk();
        self::assertTrue($preview->json('data.can_commit'));
        $this->commit(
            $eventId,
            $patch,
            (string) $preview->json('data.preview_token'),
            'maintained-blueprint',
        )->assertCreated();
        $stored = DB::table('events')->where('id', $eventId)->first();
        self::assertSame('51.50740000', (string) $stored->latitude);
        self::assertSame('-0.12780000', (string) $stored->longitude);
        self::assertSame('https://media.example.test/event', $stored->video_url);
        self::assertSame(1, (int) $stored->all_day);
        self::assertSame(1, (int) $stored->accessibility_step_free);

        $unsupported = $this->preview($eventId, ['group_id' => 99])->assertOk();
        self::assertFalse($unsupported->json('data.can_commit'));
        self::assertSame(
            'unsupported_effective_field',
            $unsupported->json('data.impact.blocking_conflicts.0.code'),
        );
        self::assertSame('group_id', $unsupported->json('data.impact.blocking_conflicts.0.field'));

        $invalid = $this->preview($eventId, ['is_online' => 'false'])
            ->assertUnprocessable();
        self::assertStringContainsString('private', (string) $invalid->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $invalid->headers->get('Cache-Control'));
        $this->preview($eventId, ['video_url' => 'ftp://example.test/video'])
            ->assertUnprocessable();
    }

    public function test_mixed_child_lifecycle_notifies_only_published_concrete_occurrences(): void
    {
        $organizer = $this->activeUser();
        $publishedAttendee = $this->activeUser();
        $draftAttendee = $this->activeUser();
        $series = $this->createSeries($organizer, '2028-04-01 09:00:00', 2);
        DB::table('events')->where('id', $series['root'])->update([
            'status' => 'active',
            'publication_status' => 'published',
        ]);
        DB::table('events')->where('id', $series['occurrences'][0])->update([
            'status' => 'active',
            'publication_status' => 'published',
        ]);
        foreach ([
            [$series['occurrences'][0], (int) $publishedAttendee->id],
            [$series['occurrences'][1], (int) $draftAttendee->id],
        ] as [$eventId, $userId]) {
            DB::table('event_rsvps')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'user_id' => $userId,
                'status' => 'going',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $preview = $this->preview($series['occurrences'][0], ['location' => 'Lifecycle venue'])
            ->assertOk();
        $commit = $this->commit(
            $series['occurrences'][0],
            ['location' => 'Lifecycle venue'],
            (string) $preview->json('data.preview_token'),
            'mixed-lifecycle',
        )->assertCreated();
        $outbox = DB::table('event_domain_outbox')
            ->where('id', (int) $commit->json('data.notification_outbox_id'))->first();
        self::assertNotNull($outbox);
        $payload = json_decode((string) $outbox->payload, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([(int) $publishedAttendee->id], $payload['affected_recipient_user_ids']);
        self::assertSame(
            [$series['occurrences'][0]],
            $payload['metadata']['series']['affected_event_ids'],
        );
        self::assertSame(
            $series['occurrences'][0],
            $payload['metadata']['series']['presentation_event_id'],
        );
    }

    public function test_revision_delivery_derives_each_recipient_concrete_preference_context(): void
    {
        config()->set('events.notification_delivery.consumer_enabled', true);
        config()->set('events.notification_delivery.channels', ['in_app']);
        $organizer = $this->activeUser();
        $firstAttendee = $this->activeUser();
        $secondAttendee = $this->activeUser();
        $series = $this->createSeries($organizer, '2028-05-01 09:00:00', 2);
        DB::table('events')
            ->where(static fn ($query) => $query
                ->where('id', $series['root'])
                ->orWhere('parent_event_id', $series['root']))
            ->update(['status' => 'active', 'publication_status' => 'published']);
        foreach ([
            [$series['occurrences'][0], (int) $firstAttendee->id],
            [$series['occurrences'][1], (int) $secondAttendee->id],
        ] as [$eventId, $userId]) {
            DB::table('event_rsvps')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'user_id' => $userId,
                'status' => 'going',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        // The first attendee opts out on their own occurrence. The second
        // attendee opts out only on the other occurrence and must still receive
        // the revision using their actual second-occurrence context.
        foreach ([$firstAttendee, $secondAttendee] as $attendee) {
            DB::table('event_notification_preferences')->insert([
                'tenant_id' => $this->testTenantId,
                'user_id' => $attendee->id,
                'event_id' => $series['occurrences'][0],
                'category_id' => null,
                'email_enabled' => null,
                'in_app_enabled' => false,
                'web_push_enabled' => null,
                'fcm_enabled' => null,
                'realtime_enabled' => null,
                'cadence' => null,
                'reminders_enabled' => null,
                'preference_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $preview = $this->preview(
            $series['occurrences'][0],
            ['location' => 'Recipient context venue'],
        )->assertOk();
        $commit = $this->commit(
            $series['occurrences'][0],
            ['location' => 'Recipient context venue'],
            (string) $preview->json('data.preview_token'),
            'recipient-context-delivery',
        )->assertCreated();
        $outboxId = (int) $commit->json('data.notification_outbox_id');
        $payload = json_decode((string) DB::table('event_domain_outbox')
            ->where('id', $outboxId)
            ->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('recipient_event_ids', $payload['metadata']['series']);

        app(EventNotificationOutboxProcessor::class)->processBatch(20, $this->testTenantId);

        self::assertSame(0, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $firstAttendee->id)
            ->where('type', 'event_update')
            ->count());
        self::assertSame(1, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $secondAttendee->id)
            ->where('type', 'event_update')
            ->where('link', '/events/' . $series['occurrences'][1])
            ->count());
        $this->assertDatabaseHas('event_notification_deliveries', [
            'tenant_id' => $this->testTenantId,
            'outbox_id' => $outboxId,
            'recipient_user_id' => $firstAttendee->id,
            'channel' => 'in_app',
            'status' => 'suppressed',
            'suppression_reason' => 'channel_disabled_event',
        ]);
    }

    public function test_schema_ledgers_are_immutable_tenant_scoped_and_protect_historical_event_ids(): void
    {
        self::assertTrue(Schema::hasTable('event_recurrence_revisions'));
        self::assertTrue(Schema::hasTable('event_recurrence_occurrence_ledger'));
        self::assertTrue(Schema::hasColumn('event_recurrence_rules', 'effective_revision_version'));
        self::assertTrue(Schema::hasColumn('event_recurrence_rules', 'materialized_set_version'));

        $organizer = $this->activeUser();
        $series = $this->createSeries($organizer, '2027-12-01 09:00:00', 1);
        $selectedId = $series['occurrences'][0];
        $preview = $this->preview($selectedId, ['location' => 'Immutable venue'])->assertOk();
        $commit = $this->commit(
            $selectedId,
            ['location' => 'Immutable venue'],
            (string) $preview->json('data.preview_token'),
            'immutable-ledger',
        )->assertCreated();
        $revisionId = (int) $commit->json('data.revision_id');

        try {
            DB::table('event_recurrence_revisions')->where('id', $revisionId)
                ->update(['patch_hash' => str_repeat('0', 64)]);
            self::fail('Revision evidence was mutable.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('event_recurrence_revision_immutable', $exception->getMessage());
        }
        try {
            DB::table('events')->where('id', $selectedId)->delete();
            self::fail('Occurrence evidence allowed physical event deletion.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('foreign key constraint', strtolower($exception->getMessage()));
        }
        self::assertSame(1, DB::table('users')->where('id', $organizer->id)->delete());
        self::assertSame((int) $organizer->id, (int) DB::table('event_recurrence_revisions')
            ->where('id', $revisionId)->value('actor_user_id'));
        self::assertSame((int) $organizer->id, (int) DB::table('event_recurrence_occurrence_ledger')
            ->where('event_id', $selectedId)->orderByDesc('state_version')->value('actor_user_id'));
    }

    public function test_every_partial_schema_artifact_permutation_fails_before_ddl(): void
    {
        EventRecurrenceRevisionSchemaGuard::assertFresh(false, false, false, false);

        for ($mask = 1; $mask < 16; $mask++) {
            try {
                EventRecurrenceRevisionSchemaGuard::assertFresh(
                    ($mask & 1) !== 0,
                    ($mask & 2) !== 0,
                    ($mask & 4) !== 0,
                    ($mask & 8) !== 0,
                );
                self::fail("Partial recurrence revision schema mask {$mask} was accepted.");
            } catch (\LogicException $exception) {
                self::assertSame(
                    'event_recurrence_revision_partial_schema_exists',
                    $exception->getMessage(),
                );
            }
        }
    }

    /** @return array{root:int,occurrences:list<int>} */
    private function createSeries(
        User $organizer,
        string $start,
        int $count,
        string $timezone = 'Europe/Dublin',
    ): array {
        Sanctum::actingAs($organizer, ['*']);
        $startDate = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
        $response = $this->apiPost('/v2/events/recurring', [
            'title' => 'V2 revision fixture',
            'description' => 'Effective-dated recurrence fixture.',
            'start_time' => $start,
            'end_time' => $startDate->modify('+1 hour')->format('Y-m-d H:i:s'),
            'timezone' => $timezone,
            'all_day' => false,
            'location' => 'Community hall',
            'is_online' => false,
            'allow_remote_attendance' => false,
            'federated_visibility' => 'none',
            'recurrence_frequency' => 'daily',
            'recurrence_ends_type' => 'after_count',
            'recurrence_ends_after_count' => $count,
        ])->assertCreated();
        $root = (int) $response->json('data.template.id');
        $occurrences = DB::table('events')
            ->where('tenant_id', $this->testTenantId)
            ->where('parent_event_id', $root)
            ->orderBy('recurrence_id')
            ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        return ['root' => $root, 'occurrences' => $occurrences];
    }

    /** @param array<string,mixed> $patch */
    private function preview(int $eventId, array $patch): \Illuminate\Testing\TestResponse
    {
        return $this->apiPost(
            "/v2/events/{$eventId}/recurrence-revisions/preview",
            ['patch' => $patch],
        );
    }

    /** @param array<string,mixed> $patch */
    private function commit(
        int $eventId,
        array $patch,
        string $token,
        string $idempotencyKey,
    ): \Illuminate\Testing\TestResponse {
        return $this->apiPost(
            "/v2/events/{$eventId}/recurrence-revisions/commit",
            ['patch' => $patch, 'preview_token' => $token],
            ['Idempotency-Key' => $idempotencyKey],
        );
    }

    private function activeUser(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }
}
