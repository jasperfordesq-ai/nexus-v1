<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Exceptions\EventTemplateException;
use App\Models\User;
use App\Services\EventTemplateService;
use App\Support\Events\EventTemplateManifest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class EventTemplateServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_capture_preview_and_snapshot_use_the_exact_allowlist_and_denylist(): void
    {
        $owner = $this->user();
        $categoryId = $this->category();
        $sourceEventId = $this->sourceEvent((int) $owner->id, [
            'category_id' => $categoryId,
            'location' => 'Secure venue',
            'accessibility_step_free' => true,
            'accessibility_toilet' => false,
            'accessibility_hearing_loop' => true,
            'accessibility_quiet_space' => true,
            'accessibility_seating' => true,
            'accessibility_parking' => null,
            'accessibility_parking_details' => 'Two blue-badge bays beside the entrance.',
            'accessibility_transit_details' => 'Low-floor bus stop 100 metres away.',
            'accessibility_assistance_contact' => 'Ask the venue welcome desk.',
            'accessibility_notes' => 'Portable ramp available as a backup.',
            'latitude' => 53.3498,
            'longitude' => -6.2603,
            'max_attendees' => 75,
            'is_online' => true,
            'allow_remote_attendance' => true,
            'online_link' => 'https://private.example.invalid/meeting/secret',
            'video_url' => 'https://private.example.invalid/recording/secret',
            'image_url' => '/uploads/tenants/hour-timebank/events/private.jpg',
            'cover_image' => '/uploads/tenants/hour-timebank/events/private-cover.jpg',
            'federated_visibility' => 'joinable',
            'lifecycle_reason' => 'private moderation detail',
            'moderation_reason' => 'private review detail',
            'series_id' => null,
        ]);
        $service = new EventTemplateService();

        $preview = $service->previewCapture($sourceEventId, $owner);
        self::assertSame(EventTemplateManifest::SCHEMA_VERSION, $preview['schema_version']);
        self::assertSame(EventTemplateManifest::COPIED_FIELDS, array_keys($preview['payload']));
        self::assertSame(EventTemplateManifest::COPIED_FIELDS, $preview['copied_fields']);
        self::assertSame(EventTemplateManifest::SKIPPED_FIELDS, $preview['skipped_fields']);
        self::assertSame('joinable', $preview['payload']['federated_visibility']);
        self::assertSame([
            'step_free_access' => true,
            'accessible_toilet' => false,
            'hearing_loop' => true,
            'quiet_space' => true,
            'seating_available' => true,
            'accessible_parking' => null,
            'parking_details' => 'Two blue-badge bays beside the entrance.',
            'transit_details' => 'Low-floor bus stop 100 metres away.',
            'assistance_contact' => 'Ask the venue welcome desk.',
            'notes' => 'Portable ramp available as a backup.',
        ], $preview['payload']['venue_accessibility']);
        self::assertFalse(in_array('online_link', array_keys($preview['payload']), true));
        self::assertFalse(in_array('start_time', array_keys($preview['payload']), true));
        self::assertFalse(in_array('id', array_keys($preview['payload']), true));

        $capture = $service->capture($sourceEventId, $owner, 'capture-exact-manifest');
        self::assertTrue($capture['created']);
        self::assertSame(1, (int) $capture['template']->current_version);
        self::assertSame(1, (int) $capture['version']->version_number);
        self::assertSame($preview['payload'], $capture['version']->payload);
        self::assertSame($preview['payload_hash'], $capture['version']->payload_hash);
        self::assertSame(EventTemplateManifest::COPIED_FIELDS, $capture['version']->copied_fields);
        self::assertSame(EventTemplateManifest::SKIPPED_FIELDS, $capture['version']->skipped_fields);
        self::assertStringNotContainsString(
            'private.example.invalid',
            json_encode($capture['version']->payload, JSON_THROW_ON_ERROR),
        );
        self::assertDatabaseCount('event_template_versions', 1);
        self::assertDatabaseCount('event_template_audit', 1);
    }

    public function test_capture_preview_fails_closed_for_feature_actor_tenant_and_policy_boundaries(): void
    {
        $owner = $this->user();
        $otherMember = $this->user();
        $inactive = $this->user(['status' => 'inactive']);
        $foreignUser = $this->user([], 999);
        $sourceEventId = $this->sourceEvent((int) $owner->id);
        $foreignEventId = $this->sourceEvent((int) $foreignUser->id, [], 999);
        $service = new EventTemplateService();

        $this->assertReason(
            fn () => $service->previewCapture($sourceEventId, $inactive),
            'event_template_actor_not_active',
        );
        $this->assertReason(
            fn () => $service->previewCapture($sourceEventId, $foreignUser),
            'event_template_actor_not_active',
        );
        $this->assertReason(
            fn () => $service->previewCapture($sourceEventId, $otherMember),
            'event_template_authorization_denied',
        );
        $this->assertReason(
            fn () => $service->previewCapture($foreignEventId, $owner),
            'event_template_source_not_found',
        );

        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => false], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertReason(
            fn () => $service->previewCapture($sourceEventId, $owner),
            'event_template_feature_disabled',
        );
    }

    public function test_capture_revision_archive_and_idempotency_are_stable_and_versioned(): void
    {
        $owner = $this->user();
        $sourceEventId = $this->sourceEvent((int) $owner->id);
        $otherSourceEventId = $this->sourceEvent((int) $owner->id, ['title' => 'Other source']);
        $service = new EventTemplateService();

        $first = $service->capture($sourceEventId, $owner, 'stable-capture-key');
        $captureReplay = $service->capture($sourceEventId, $owner, 'stable-capture-key');
        self::assertFalse($captureReplay['created']);
        self::assertSame((int) $first['template']->id, (int) $captureReplay['template']->id);
        self::assertSame((int) $first['version']->id, (int) $captureReplay['version']->id);
        $this->assertReason(
            fn () => $service->capture($otherSourceEventId, $owner, 'stable-capture-key'),
            'event_template_idempotency_conflict',
        );

        DB::table('events')->where('id', $sourceEventId)->update([
            'title' => 'Revised safe configuration',
            'updated_at' => now(),
        ]);
        $revised = $service->revise(
            (int) $first['template']->id,
            $owner,
            1,
            'stable-revision-key',
        );
        self::assertTrue($revised['changed']);
        self::assertSame(2, (int) $revised['template']->current_version);
        self::assertSame(2, (int) $revised['version']->version_number);
        self::assertSame('Revised safe configuration', $revised['version']->payload['title']);

        DB::table('events')->where('id', $sourceEventId)->update([
            'title' => 'Changed after the idempotent revision',
            'updated_at' => now(),
        ]);
        $revisionReplay = $service->revise(
            (int) $first['template']->id,
            $owner,
            1,
            'stable-revision-key',
        );
        self::assertFalse($revisionReplay['changed']);
        self::assertSame((int) $revised['version']->id, (int) $revisionReplay['version']->id);
        self::assertSame('Revised safe configuration', $revisionReplay['version']->payload['title']);
        $this->assertReason(
            fn () => $service->revise(
                (int) $first['template']->id,
                $owner,
                1,
                'stale-revision-key',
            ),
            'event_template_version_conflict',
        );
        $this->assertReason(
            fn () => $service->previewMaterialization(
                (int) $first['template']->id,
                1,
                $owner,
                CarbonImmutable::now('UTC')->addMonths(2),
                CarbonImmutable::now('UTC')->addMonths(2)->addHours(2),
            ),
            'event_template_version_stale',
        );

        $archived = $service->archive(
            (int) $first['template']->id,
            $owner,
            2,
            'Superseded by a controlled programme template',
            'stable-archive-key',
        );
        self::assertTrue($archived['changed']);
        self::assertSame('archived', $archived['template']->getRawOriginal('status'));
        $archiveReplay = $service->archive(
            (int) $first['template']->id,
            $owner,
            2,
            'Superseded by a controlled programme template',
            'stable-archive-key',
        );
        self::assertFalse($archiveReplay['changed']);
        $this->assertReason(
            fn () => $service->revise(
                (int) $first['template']->id,
                $owner,
                2,
                'revision-after-archive',
            ),
            'event_template_archived',
        );
        self::assertDatabaseCount('event_template_versions', 2);
        self::assertDatabaseCount('event_template_audit', 3);
    }

    private function user(array $overrides = [], int $tenantId = 2): User
    {
        return User::factory()->forTenant($tenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    private function category(): int
    {
        $suffix = bin2hex(random_bytes(6));

        return (int) DB::table('categories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Event template ' . $suffix,
            'slug' => 'event-template-' . $suffix,
            'type' => 'event',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $overrides */
    private function sourceEvent(int $ownerId, array $overrides = [], int $tenantId = 2): int
    {
        $start = CarbonImmutable::now('UTC')->addMonth()->startOfHour();

        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'title' => 'Template source event',
            'description' => 'Reusable approved configuration.',
            'location' => null,
            'latitude' => null,
            'longitude' => null,
            'start_time' => $start,
            'end_time' => $start->addHours(2),
            'timezone' => 'UTC',
            'timezone_source' => 'test',
            'all_day' => false,
            'category_id' => null,
            'group_id' => null,
            'max_attendees' => 40,
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
            'lifecycle_version' => 4,
            'calendar_sequence' => 2,
            'is_recurring_template' => false,
            'occurrence_key' => "template-source:{$tenantId}:" . bin2hex(random_bytes(12)),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** @param callable():mixed $operation */
    private function assertReason(callable $operation, string $reason): void
    {
        try {
            $operation();
            self::fail("Expected {$reason}.");
        } catch (EventTemplateException $exception) {
            self::assertSame($reason, $exception->reasonCode);
        }
    }
}
