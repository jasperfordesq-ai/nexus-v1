<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\CommunityEventCreated;
use App\Events\CommunityEventUpdated;
use App\Listeners\PushCommunityEventToFederatedPartners;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\TestCase;

/** Compatibility coverage for callers that still invoke the retired listener. */
final class PushCommunityEventToFederatedPartnersTest extends TestCase
{
    use DatabaseTransactions;

    private PushCommunityEventToFederatedPartners $listener;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        $this->enableEventFederation();
        $this->listener = app(PushCommunityEventToFederatedPartners::class);
        Http::fake();
    }

    public function test_legacy_created_and_updated_dispatches_only_enqueue_one_idempotent_delivery(): void
    {
        $partnerId = $this->partner('legacy-idempotent');
        $event = $this->event('listed');

        $this->listener->handle(new CommunityEventCreated($event, $this->testTenantId));
        $this->listener->handle(new CommunityEventUpdated($event, $this->testTenantId));

        self::assertSame(1, DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('external_partner_id', $partnerId)
            ->count());
        self::assertSame('upsert', DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->value('action'));
        Http::assertNothingSent();
    }

    public function test_joinable_visibility_uses_the_same_strict_public_contract(): void
    {
        $this->partner('joinable');
        $event = $this->event('joinable');

        $this->listener->handle(new CommunityEventCreated($event, $this->testTenantId));

        $stored = DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->first();
        self::assertNotNull($stored);
        $payload = json_decode((string) $stored->payload, true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('joinable', $payload['visibility']);
        foreach (['description', 'online_link', 'user_id', 'registration_roster'] as $privateKey) {
            self::assertArrayNotHasKey($privateKey, $payload);
        }
        Http::assertNothingSent();
    }

    public function test_non_federated_or_disabled_event_creates_no_new_partner_delivery(): void
    {
        $this->partner('disabled');
        $private = $this->event('none');
        $this->listener->handle(new CommunityEventCreated($private, $this->testTenantId));

        DB::table('federation_system_control')->where('id', 1)->update([
            'federation_enabled' => 0,
        ]);
        $listed = $this->event('listed');
        $this->listener->handle(new CommunityEventCreated($listed, $this->testTenantId));

        self::assertSame(0, DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->whereIn('event_id', [$private->id, $listed->id])
            ->count());
        Http::assertNothingSent();
    }

    public function test_unknown_tenant_is_ignored_and_cli_context_is_cleared(): void
    {
        $event = $this->event('listed');

        $this->listener->handle(new CommunityEventCreated($event, 99999999));

        self::assertNull(TenantContext::currentId());
        self::assertTrue(TenantContext::setById($this->testTenantId));
        self::assertSame(0, DB::table('event_federation_deliveries')
            ->where('event_id', $event->id)
            ->count());
        Http::assertNothingSent();
    }

    public function test_listener_remains_on_the_federation_queue_for_legacy_callers(): void
    {
        self::assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $this->listener);
        self::assertSame('federation', $this->listener->queue);
    }

    private function event(string $visibility): Event
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        return Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $organizer->id,
            'title' => 'Legacy compatibility event',
            'description' => 'Private long-form detail',
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(2),
            'timezone' => 'UTC',
            'all_day' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'federated_visibility' => $visibility,
            'lifecycle_version' => 8,
            'calendar_sequence' => 3,
            'federation_version' => 8,
            'is_recurring_template' => false,
        ]);
    }

    private function partner(string $suffix): int
    {
        return (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Legacy event partner ' . $suffix,
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
