<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events\Federation;

use App\Core\TenantContext;
use App\Enums\EventOperationalState;
use App\Enums\EventPublicationState;
use App\Models\Event;
use App\Models\User;
use App\Services\EventFederationPublisher;
use App\Services\EventLifecycleService;
use App\Services\EventPublicationWorkflowService;
use App\Services\EventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class EventFederationLifecycleIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        $this->enableEventFederation();
    }

    public function test_publish_and_cancel_create_ordered_upsert_and_tombstone_facts(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer, [
            'status' => 'draft',
            'publication_status' => 'draft',
            'lifecycle_version' => 0,
            'federation_version' => 1,
        ]);
        $partnerId = $this->partner('lifecycle');
        $lifecycle = app(EventLifecycleService::class);

        $published = $lifecycle->transition(
            (int) $event->id,
            $organizer,
            EventPublicationState::Published,
        );
        $cancelled = $lifecycle->transition(
            (int) $event->id,
            $organizer,
            null,
            EventOperationalState::Cancelled,
            'Venue unavailable',
        );

        self::assertTrue($published->changed);
        self::assertTrue($cancelled->changed);
        $deliveries = DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('external_partner_id', $partnerId)
            ->orderBy('event_aggregate_version')
            ->get();
        self::assertCount(2, $deliveries);
        self::assertSame(['upsert', 'tombstone'], $deliveries->pluck('action')->all());
        self::assertSame([2, 3], $deliveries->pluck('event_aggregate_version')
            ->map(static fn (mixed $version): int => (int) $version)
            ->all());
        $tombstone = json_decode((string) $deliveries[1]->payload, true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('cancelled', $tombstone['tombstone_reason']);
        self::assertArrayNotHasKey('online_link', $tombstone);
        self::assertSame(3, (int) DB::table('events')->where('id', $event->id)->value('federation_version'));
    }

    public function test_recurring_publication_suppresses_template_federation_but_upserts_occurrences(): void
    {
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->whereIn('setting_key', ['moderation.enabled', 'moderation.require_event'])
            ->delete();
        $organizer = $this->organizer();
        $template = $this->event($organizer, [
            'title' => 'Federated recurring template',
            'status' => 'draft',
            'publication_status' => 'draft',
            'lifecycle_version' => 0,
            'federation_version' => 1,
            'is_recurring_template' => true,
        ]);
        $occurrence = $this->event($organizer, [
            'title' => 'Federated recurring occurrence',
            'status' => 'draft',
            'publication_status' => 'draft',
            'lifecycle_version' => 0,
            'federation_version' => 1,
            'parent_event_id' => $template->id,
            'is_recurring_template' => false,
        ]);
        $partnerId = $this->partner('recurring-publication');

        app(EventPublicationWorkflowService::class)->publish((int) $occurrence->id, $organizer);

        self::assertSame(0, DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $template->id)
            ->count());
        $delivery = DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $occurrence->id)
            ->where('external_partner_id', $partnerId)
            ->first();
        self::assertNotNull($delivery);
        self::assertSame('upsert', $delivery->action);
    }

    public function test_direct_publisher_retracts_historical_template_leaks(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $partnerId = $this->partner('template-retraction');
        $publisher = app(EventFederationPublisher::class);
        $publisher->publish($event);
        DB::table('events')->where('id', $event->id)->update(['is_recurring_template' => 1]);
        $event->refresh();

        $publisher->publish($event);

        $deliveries = DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('external_partner_id', $partnerId)
            ->orderBy('id')
            ->get();
        self::assertSame(['upsert', 'tombstone'], $deliveries->pluck('action')->all());
        $payload = json_decode((string) $deliveries->last()->payload, true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('unpublished', $payload['tombstone_reason']);
    }

    public function test_event_update_never_upserts_a_recurring_template_without_prior_evidence(): void
    {
        $organizer = $this->organizer();
        $template = $this->event($organizer, ['is_recurring_template' => true]);
        $this->partner('template-update');

        self::assertFalse(EventService::update((int) $template->id, (int) $organizer->id, [
            'federated_visibility' => 'joinable',
        ]));
        self::assertSame('EVENT_RECURRENCE_SCOPE_REQUIRED', EventService::getErrors()[0]['code']);

        self::assertSame(0, DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $template->id)
            ->count());
    }

    public function test_visibility_withdrawal_advances_version_and_enqueues_retraction(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $partnerId = $this->partner('visibility');
        app(EventFederationPublisher::class)->publish($event);

        self::assertTrue(EventService::update((int) $event->id, (int) $organizer->id, [
            'federated_visibility' => 'none',
        ]));

        $deliveries = DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('external_partner_id', $partnerId)
            ->orderBy('event_aggregate_version')
            ->get();
        self::assertCount(2, $deliveries);
        self::assertSame('upsert', $deliveries[0]->action);
        self::assertSame('tombstone', $deliveries[1]->action);
        self::assertSame(9, (int) $deliveries[1]->event_aggregate_version);
        $payload = json_decode((string) $deliveries[1]->payload, true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('visibility_withdrawn', $payload['tombstone_reason']);
    }

    public function test_meeting_link_only_edit_never_creates_a_federation_fact_or_version(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer, [
            'is_online' => true,
            'online_link' => 'https://meet.example.test/first?token=private-one',
        ]);
        $this->partner('meeting-link');
        app(EventFederationPublisher::class)->publish($event);
        $before = DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->count();

        self::assertTrue(EventService::update((int) $event->id, (int) $organizer->id, [
            'online_link' => 'https://meet.example.test/second?token=private-two',
        ]));

        self::assertSame($before, DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->count());
        self::assertSame(8, (int) DB::table('events')->where('id', $event->id)->value('federation_version'));
        $encoded = DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->value('payload');
        self::assertStringNotContainsString('private-one', (string) $encoded);
        self::assertStringNotContainsString('private-two', (string) $encoded);
    }

    public function test_physical_deletion_records_final_tombstone_with_no_event_row_dependency(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer);
        $partnerId = $this->partner('physical-delete');
        app(EventFederationPublisher::class)->publish($event);

        $event->delete();

        self::assertDatabaseMissing('events', ['id' => $event->id, 'tenant_id' => $this->testTenantId]);
        $latest = DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('external_partner_id', $partnerId)
            ->orderByDesc('event_aggregate_version')
            ->first();
        self::assertNotNull($latest);
        self::assertSame('tombstone', $latest->action);
        self::assertSame(9, (int) $latest->event_aggregate_version);
        $payload = json_decode((string) $latest->payload, true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('deleted', $payload['tombstone_reason']);
    }

    private function organizer(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    /** @param array<string,mixed> $overrides */
    private function event(User $organizer, array $overrides = []): Event
    {
        return Event::factory()->forTenant($this->testTenantId)->create(array_merge([
            'user_id' => $organizer->id,
            'title' => 'Phase B lifecycle event',
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(5)->addHours(2),
            'timezone' => 'UTC',
            'all_day' => false,
            'latitude' => 53.3,
            'longitude' => -6.2,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'federated_visibility' => 'listed',
            'lifecycle_version' => 8,
            'calendar_sequence' => 3,
            'federation_version' => 8,
            'is_recurring_template' => false,
        ], $overrides));
    }

    private function partner(string $suffix): int
    {
        return (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Phase B lifecycle partner ' . $suffix,
            'base_url' => 'https://' . $suffix . '-' . uniqid() . '.example.test',
            'api_path' => '/api/v2/federation',
            'auth_method' => 'api_key',
            'protocol_type' => 'nexus',
            'status' => 'active',
            'allow_events' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function enableEventFederation(): void
    {
        DB::table('federation_system_control')->updateOrInsert(
            ['id' => 1],
            [
                'federation_enabled' => 1,
                'whitelist_mode_enabled' => 0,
                'emergency_lockdown_active' => 0,
                'max_federation_level' => 4,
                'cross_tenant_profiles_enabled' => 1,
                'cross_tenant_messaging_enabled' => 1,
                'cross_tenant_transactions_enabled' => 1,
                'cross_tenant_listings_enabled' => 1,
                'cross_tenant_events_enabled' => 1,
                'cross_tenant_groups_enabled' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        foreach (['tenant_federation_enabled', 'tenant_events_enabled'] as $feature) {
            DB::table('federation_tenant_features')->updateOrInsert(
                ['tenant_id' => $this->testTenantId, 'feature_key' => $feature],
                ['is_enabled' => 1, 'updated_at' => now()],
            );
        }
    }
}
