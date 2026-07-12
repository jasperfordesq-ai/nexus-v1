<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Exceptions\EventTemplateException;
use App\Models\User;
use App\Services\EventTemplateService;
use App\Support\Events\EventTemplateManifest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Laravel\TestCase;

final class EventTemplateMaterializationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_preview_is_write_free_and_materialization_creates_only_one_fresh_draft(): void
    {
        $owner = $this->user();
        $attendee = $this->user();
        $waitlisted = $this->user();
        $staff = $this->user();
        $sourceStart = CarbonImmutable::now('UTC')->addMonth()->startOfHour();
        $sourceEventId = $this->sourceEvent((int) $owner->id, [
            'start_time' => $sourceStart,
            'end_time' => $sourceStart->addHours(3),
            'location' => 'Programme venue',
            'accessibility_step_free' => true,
            'accessibility_toilet' => true,
            'accessibility_hearing_loop' => false,
            'accessibility_quiet_space' => true,
            'accessibility_seating' => true,
            'accessibility_parking' => true,
            'accessibility_parking_details' => 'Reserved spaces at the north entrance.',
            'accessibility_transit_details' => 'Step-free rail station nearby.',
            'accessibility_assistance_contact' => 'Call the venue reception.',
            'accessibility_notes' => 'Assistance dogs are welcome.',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'max_attendees' => 120,
            'is_online' => true,
            'allow_remote_attendance' => true,
            'online_link' => 'https://private.example.invalid/join/source-only',
            'video_url' => 'https://private.example.invalid/video/source-only',
            'image_url' => '/uploads/tenants/hour-timebank/events/source-only.jpg',
            'cover_image' => '/uploads/tenants/hour-timebank/events/source-cover-only.jpg',
            'federated_visibility' => 'joinable',
        ]);
        $populatedTables = $this->seedOperationalAndPrivateRows(
            $sourceEventId,
            $owner,
            $attendee,
            $waitlisted,
            $staff,
            $sourceStart,
        );
        $service = new EventTemplateService();
        $capture = $service->capture($sourceEventId, $owner, 'materialization-capture');
        $templateId = (int) $capture['template']->id;
        $newStart = CarbonImmutable::now('UTC')->addMonths(3)->startOfHour();
        $newEnd = $newStart->addHours(4);
        $overrides = [
            'title' => 'Controlled programme copy',
            'max_attendees' => 60,
        ];
        $eventCountBeforePreview = DB::table('events')->count();

        $preview = $service->previewMaterialization(
            $templateId,
            1,
            $owner,
            $newStart,
            $newEnd,
            $overrides,
        );
        self::assertSame($eventCountBeforePreview, DB::table('events')->count());
        self::assertSame(EventTemplateManifest::COPIED_FIELDS, $preview['copied_fields']);
        self::assertSame(EventTemplateManifest::SKIPPED_FIELDS, $preview['skipped_fields']);
        self::assertSame(['title', 'max_attendees'], $preview['override_fields']);
        self::assertSame('none', $preview['effective_payload']['federated_visibility']);
        self::assertTrue($preview['effective_payload']['venue_accessibility']['step_free_access']);
        self::assertSame(
            'Reserved spaces at the north entrance.',
            $preview['effective_payload']['venue_accessibility']['parking_details'],
        );
        self::assertSame('draft', $preview['will_create']['publication_status']);
        self::assertFalse($preview['will_create']['publish']);
        self::assertFalse($preview['will_create']['register']);
        self::assertFalse($preview['will_create']['notify']);
        self::assertFalse($preview['will_create']['federate']);
        self::assertTrue(collect($preview['checklist'])->every('passed'));

        $result = $service->materialize(
            $templateId,
            1,
            $owner,
            $newStart,
            $newEnd,
            $overrides,
            'stable-materialization-key',
        );
        self::assertTrue($result['created']);
        $clone = $result['event'];
        self::assertNotSame($sourceEventId, (int) $clone->id);
        self::assertSame('Controlled programme copy', $clone->title);
        self::assertSame('draft', $clone->getRawOriginal('status'));
        self::assertSame('draft', $clone->getRawOriginal('publication_status'));
        self::assertSame('scheduled', $clone->getRawOriginal('operational_status'));
        self::assertSame('none', $clone->getRawOriginal('federated_visibility'));
        self::assertFalse((bool) $clone->getRawOriginal('is_recurring_template'));
        self::assertNull($clone->getRawOriginal('parent_event_id'));
        self::assertNull($clone->getRawOriginal('series_id'));
        self::assertNull($clone->getRawOriginal('online_link'));
        self::assertNull($clone->getRawOriginal('video_url'));
        self::assertNull($clone->getRawOriginal('image_url'));
        self::assertNull($clone->getRawOriginal('cover_image'));
        self::assertSame(60, (int) $clone->max_attendees);
        self::assertTrue((bool) $clone->getRawOriginal('accessibility_step_free'));
        self::assertTrue((bool) $clone->getRawOriginal('accessibility_toilet'));
        self::assertFalse((bool) $clone->getRawOriginal('accessibility_hearing_loop'));
        self::assertSame(
            'Reserved spaces at the north entrance.',
            $clone->getRawOriginal('accessibility_parking_details'),
        );
        self::assertSame(
            'Assistance dogs are welcome.',
            $clone->getRawOriginal('accessibility_notes'),
        );
        self::assertSame($newStart->format('Y-m-d H:i:s'), $clone->getRawOriginal('start_time'));
        self::assertSame($newEnd->format('Y-m-d H:i:s'), $clone->getRawOriginal('end_time'));
        self::assertSame((int) $owner->id, (int) $clone->user_id);
        self::assertSame($sourceEventId, (int) $result['materialization']->source_event_id);
        self::assertSame($templateId, (int) $result['materialization']->template_id);
        self::assertSame(1, (int) $result['materialization']->template_version_number);
        self::assertTrue((bool) $result['materialization']->federation_normalized);

        foreach ($populatedTables as $table) {
            self::assertGreaterThan(
                0,
                DB::table($table)->where('event_id', $sourceEventId)->count(),
                "{$table} source fixture",
            );
        }
        foreach ($this->eventRelatedTables() as $table) {
            self::assertSame(
                0,
                DB::table($table)->where('event_id', (int) $clone->id)->count(),
                "{$table} must not be copied",
            );
        }

        $eventCountAfterCreate = DB::table('events')->count();
        $replay = $service->materialize(
            $templateId,
            1,
            $owner,
            $newStart,
            $newEnd,
            $overrides,
            'stable-materialization-key',
        );
        self::assertFalse($replay['created']);
        self::assertSame((int) $clone->id, (int) $replay['event']->id);
        self::assertSame($eventCountAfterCreate, DB::table('events')->count());
        $this->assertTemplateReason(
            fn () => $service->materialize(
                $templateId,
                1,
                $owner,
                $newStart->addDay(),
                $newEnd->addDay(),
                $overrides,
                'stable-materialization-key',
            ),
            'event_template_idempotency_conflict',
        );

        DB::table('events')->where('id', $sourceEventId)->update([
            'title' => 'A later template version',
            'updated_at' => now(),
        ]);
        $service->revise($templateId, $owner, 1, 'materialization-revision');
        $stableReplay = $service->materialize(
            $templateId,
            1,
            $owner,
            $newStart,
            $newEnd,
            $overrides,
            'stable-materialization-key',
        );
        self::assertFalse($stableReplay['created']);
        self::assertSame((int) $clone->id, (int) $stableReplay['event']->id);
        $this->assertTemplateReason(
            fn () => $service->materialize(
                $templateId,
                1,
                $owner,
                $newStart,
                $newEnd,
                $overrides,
                'new-key-for-stale-version',
            ),
            'event_template_version_stale',
        );
    }

    public function test_stale_category_and_group_are_rejected_by_the_canonical_event_writer(): void
    {
        $owner = $this->user();
        $categoryId = $this->category();
        $categorySource = $this->sourceEvent((int) $owner->id, ['category_id' => $categoryId]);
        $service = new EventTemplateService();
        $categoryTemplate = $service->capture(
            $categorySource,
            $owner,
            'stale-category-capture',
        );
        DB::table('categories')->where('id', $categoryId)->update(['is_active' => false]);
        $eventsBeforeCategoryAttempt = DB::table('events')->count();
        $this->assertCanonicalValidationFailure(fn () => $service->materialize(
            (int) $categoryTemplate['template']->id,
            1,
            $owner,
            CarbonImmutable::now('UTC')->addMonths(2),
            CarbonImmutable::now('UTC')->addMonths(2)->addHours(2),
            [],
            'stale-category-materialization',
        ));
        self::assertSame($eventsBeforeCategoryAttempt, DB::table('events')->count());

        $groupId = $this->group((int) $owner->id);
        $groupSource = $this->sourceEvent((int) $owner->id);
        $groupTemplate = $service->capture($groupSource, $owner, 'stale-group-capture');
        DB::table('groups')->where('id', $groupId)->update([
            'status' => 'archived',
            'is_active' => false,
            'updated_at' => now(),
        ]);
        $eventsBeforeGroupAttempt = DB::table('events')->count();
        $this->assertCanonicalValidationFailure(fn () => $service->materialize(
            (int) $groupTemplate['template']->id,
            1,
            $owner,
            CarbonImmutable::now('UTC')->addMonths(4),
            CarbonImmutable::now('UTC')->addMonths(4)->addHours(2),
            ['group_id' => $groupId],
            'stale-group-materialization',
        ));
        self::assertSame($eventsBeforeGroupAttempt, DB::table('events')->count());
    }

    public function test_materialization_preview_rechecks_active_persisted_actor_and_manage_policy(): void
    {
        $owner = $this->user();
        $otherMember = $this->user();
        $sourceEventId = $this->sourceEvent((int) $owner->id);
        $service = new EventTemplateService();
        $capture = $service->capture($sourceEventId, $owner, 'materialization-auth-capture');
        $templateId = (int) $capture['template']->id;
        $start = CarbonImmutable::now('UTC')->addMonths(2);
        $end = $start->addHours(2);

        $this->assertTemplateReason(
            fn () => $service->previewMaterialization(
                $templateId,
                1,
                $otherMember,
                $start,
                $end,
            ),
            'event_template_authorization_denied',
        );
        DB::table('users')->where('id', $owner->id)->update(['status' => 'inactive']);
        $this->assertTemplateReason(
            fn () => $service->materialize(
                $templateId,
                1,
                $owner,
                $start,
                $end,
                [],
                'inactive-actor-materialization',
            ),
            'event_template_actor_not_active',
        );
    }

    public function test_non_utc_materialization_preserves_the_caller_instant(): void
    {
        $owner = $this->user();
        $sourceEventId = $this->sourceEvent((int) $owner->id, [
            'timezone' => 'Europe/Dublin',
            'timezone_source' => 'test',
        ]);
        $service = new EventTemplateService();
        $capture = $service->capture($sourceEventId, $owner, 'non-utc-capture');
        $localStart = CarbonImmutable::now('Europe/Dublin')
            ->addMonths(5)
            ->startOfDay()
            ->addHours(9);
        $localEnd = $localStart->addHours(2);

        $result = $service->materialize(
            (int) $capture['template']->id,
            1,
            $owner,
            $localStart,
            $localEnd,
            [],
            'non-utc-materialization',
        );

        self::assertSame('Europe/Dublin', $result['event']->getRawOriginal('timezone'));
        self::assertSame(
            $localStart->utc()->format('Y-m-d H:i:s'),
            $result['event']->getRawOriginal('start_time'),
        );
        self::assertSame(
            $localEnd->utc()->format('Y-m-d H:i:s'),
            $result['event']->getRawOriginal('end_time'),
        );
    }

    /** @return list<string> */
    private function seedOperationalAndPrivateRows(
        int $eventId,
        User $owner,
        User $attendee,
        User $waitlisted,
        User $staff,
        CarbonImmutable $start,
    ): array {
        $now = CarbonImmutable::now('UTC');
        $registrationId = (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $attendee->id,
            'capacity_pool_key' => 'event',
            'registration_state' => 'confirmed',
            'registration_version' => 1,
            'state_changed_at' => $now,
            'state_changed_by' => (int) $owner->id,
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $attendee->id,
            'status' => 'going',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event_waitlist_entries')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $waitlisted->id,
            'capacity_pool_key' => 'event',
            'queue_state' => 'waiting',
            'queue_version' => 1,
            'queue_sequence' => 1,
            'state_changed_at' => $now,
            'state_changed_by' => (int) $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event_staff_assignments')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $staff->id,
            'role' => 'check_in_staff',
            'status' => 'active',
            'assignment_version' => 1,
            'granted_at' => $now,
            'granted_by' => (int) $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $attendanceId = (int) DB::table('event_attendance')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $attendee->id,
            'attendance_status' => 'checked_in',
            'attendance_version' => 1,
            'checked_in_at' => $now,
            'checked_in_by' => (int) $staff->id,
            'hours_credited' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event_attendance_activity')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'attendance_id' => $attendanceId,
            'user_id' => (int) $attendee->id,
            'actor_user_id' => (int) $staff->id,
            'attendance_version' => 1,
            'action' => 'check_in',
            'to_status' => 'checked_in',
            'idempotency_key' => 'template-fixture:' . bin2hex(random_bytes(8)),
            'metadata' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
        DB::table('event_sessions')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'version' => 1,
            'title' => 'Private operational session',
            'description' => 'Must not be copied.',
            'session_type' => 'session',
            'visibility' => 'registered',
            'status' => 'scheduled',
            'starts_at_utc' => $start->addMinutes(30),
            'ends_at_utc' => $start->addMinutes(90),
            'timezone' => 'UTC',
            'position' => 1,
            'created_by' => (int) $owner->id,
            'updated_by' => (int) $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->seedPrivateFormAnswer($eventId, $registrationId, $owner, $attendee, $start);

        return [
            'event_rsvps',
            'event_registrations',
            'event_waitlist_entries',
            'event_staff_assignments',
            'event_attendance',
            'event_attendance_activity',
            'event_sessions',
            'event_registration_form_answers',
        ];
    }

    private function seedPrivateFormAnswer(
        int $eventId,
        int $registrationId,
        User $owner,
        User $attendee,
        CarbonImmutable $start,
    ): void {
        $now = CarbonImmutable::now('UTC');
        $occurrenceKey = (string) DB::table('events')->where('id', $eventId)->value('occurrence_key');
        DB::table('event_registration_settings')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'occurrence_key' => $occurrenceKey,
            'revision' => 1,
            'status' => 'draft',
            'approval_mode' => 'manual',
            'event_starts_at_utc_snapshot' => $start,
            'event_timezone_snapshot' => 'UTC',
            'per_member_limit' => 1,
            'guests_enabled' => false,
            'max_guests_per_registration' => 0,
            'guest_retention_days' => 30,
            'form_state' => 'none',
            'published_form_version' => null,
            'created_by' => (int) $owner->id,
            'updated_by' => (int) $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $formId = (int) DB::table('event_registration_form_versions')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'version_number' => 1,
            'revision' => 1,
            'status' => 'draft',
            'name' => 'Private registration form',
            'description' => null,
            'definition_hash' => null,
            'created_by' => (int) $owner->id,
            'updated_by' => (int) $owner->id,
            'published_by' => null,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $questionId = (int) DB::table('event_registration_form_questions')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'form_version_id' => $formId,
            'stable_key' => 'private_access_need',
            'position' => 1,
            'question_type' => 'accessibility',
            'prompt' => 'Private accessibility requirement',
            'help_text' => null,
            'is_required' => false,
            'data_classification' => 'sensitive',
            'purpose' => 'Safe event delivery',
            'retention_days' => 30,
            'choice_options' => null,
            'displayed_text' => null,
            'displayed_text_version' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event_registration_form_versions')->where('id', $formId)->update([
            'revision' => 2,
            'status' => 'published',
            'definition_hash' => hash('sha256', 'private-form-fixture'),
            'published_by' => (int) $owner->id,
            'published_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event_registration_settings')->where('event_id', $eventId)->update([
            'revision' => 2,
            'status' => 'published',
            'form_state' => 'published',
            'published_form_version' => 1,
            'published_by' => (int) $owner->id,
            'published_at' => $now,
            'updated_by' => (int) $owner->id,
            'updated_at' => $now,
        ]);
        $submissionId = (int) DB::table('event_registration_form_submissions')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'registration_id' => $registrationId,
            'user_id' => (int) $attendee->id,
            'form_version_id' => $formId,
            'revision' => 1,
            'status' => 'draft',
            'submitted_at' => null,
            'withdrawn_at' => null,
            'anonymised_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event_registration_form_answers')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'submission_id' => $submissionId,
            'form_version_id' => $formId,
            'question_id' => $questionId,
            'data_classification' => 'sensitive',
            'answer_ciphertext' => 'encrypted-private-fixture',
            'retention_due_at' => $now->addDays(30),
            'consented_at' => null,
            'displayed_text_hash' => null,
            'displayed_text_version' => null,
            'purged_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return list<string> */
    private function eventRelatedTables(): array
    {
        $known = [
            'event_rsvps',
            'event_registrations',
            'event_registration_history',
            'event_waitlist_entries',
            'event_waitlist_entry_history',
            'event_staff_assignments',
            'event_staff_assignment_history',
            'event_attendance',
            'event_attendance_activity',
            'event_attendance_credit_claims',
            'event_sessions',
            'event_session_history',
            'event_session_speakers',
            'event_registration_settings',
            'event_registration_form_versions',
            'event_registration_form_questions',
            'event_registration_form_submissions',
            'event_registration_form_answers',
            'event_registration_guests',
            'event_invitation_campaigns',
            'event_invitations',
            'event_checkin_credentials',
            'event_checkin_devices',
            'event_offline_sync_batches',
            'event_offline_sync_items',
            'event_offline_sync_decisions',
            'event_reminder_rules',
            'event_reminder_schedules',
            'event_reminder_delivery_claims',
            'event_domain_outbox',
            'event_federation_deliveries',
        ];
        if (DB::getDriverName() === 'mysql') {
            $known = array_merge($known, DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'like', 'event\_%')
                ->where('COLUMN_NAME', 'event_id')
                ->pluck('TABLE_NAME')
                ->map(static fn (mixed $table): string => (string) $table)
                ->all());
        }

        return array_values(array_filter(
            array_values(array_unique($known)),
            static fn (string $table): bool => Schema::hasTable($table)
                && Schema::hasColumn($table, 'event_id'),
        ));
    }

    private function user(array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    private function category(): int
    {
        $suffix = bin2hex(random_bytes(6));

        return (int) DB::table('categories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Template category ' . $suffix,
            'slug' => 'template-category-' . $suffix,
            'type' => 'event',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function group(int $ownerId): int
    {
        $suffix = bin2hex(random_bytes(6));

        return (int) DB::table('groups')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'owner_id' => $ownerId,
            'name' => 'Template group ' . $suffix,
            'slug' => 'template-group-' . $suffix,
            'description' => 'Template group fixture.',
            'visibility' => 'public',
            'status' => 'active',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $overrides */
    private function sourceEvent(int $ownerId, array $overrides = []): int
    {
        $start = CarbonImmutable::now('UTC')->addMonth()->startOfHour();

        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'title' => 'Operational template source',
            'description' => 'Only approved configuration may be cloned.',
            'location' => null,
            'latitude' => null,
            'longitude' => null,
            'start_time' => $start,
            'end_time' => $start->addHours(3),
            'timezone' => 'UTC',
            'timezone_source' => 'test',
            'all_day' => false,
            'category_id' => null,
            'group_id' => null,
            'max_attendees' => 100,
            'is_online' => false,
            'allow_remote_attendance' => false,
            'online_link' => null,
            'video_url' => null,
            'image_url' => null,
            'cover_image' => null,
            'federated_visibility' => 'none',
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 7,
            'calendar_sequence' => 3,
            'agenda_version' => 0,
            'is_recurring_template' => false,
            'occurrence_key' => 'template-materialize:' . bin2hex(random_bytes(12)),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** @param callable():mixed $operation */
    private function assertTemplateReason(callable $operation, string $reason): void
    {
        try {
            $operation();
            self::fail("Expected {$reason}.");
        } catch (EventTemplateException $exception) {
            self::assertSame($reason, $exception->reasonCode);
        }
    }

    /** @param callable():mixed $operation */
    private function assertCanonicalValidationFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected the canonical EventService writer to reject a stale association.');
        } catch (ValidationException $exception) {
            self::assertNotEmpty($exception->errors());
        }
    }
}
