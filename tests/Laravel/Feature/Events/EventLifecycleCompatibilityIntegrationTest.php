<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class EventLifecycleCompatibilityIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
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
        self::assertSame(1, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $attendee->id)
            ->where('link', "/events/{$templateId}")
            ->where('type', 'event')
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
            ->where('link', "/events/{$templateId}")
            ->where('type', 'event')
            ->count());
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
