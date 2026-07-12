<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Enums\EventNotificationDeliveryMode;
use App\Models\User;
use App\Services\EventDomainOutboxService;
use App\Services\EventNotificationDeliveryModeResolver;
use App\Services\EventNotificationOutboxConsumer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Laravel\TestCase;

final class EventOutboxFoundationTest extends TestCase
{
    use DatabaseTransactions;

    private EventDomainOutboxService $outbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outbox = new EventDomainOutboxService();
        Config::set('events.notification_delivery.consumer_enabled', false);
    }

    private function member(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function eventOwnedBy(int $organizerId): int
    {
        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Outbox foundation event',
            'description' => 'Durable event notification fixture.',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_expand_only_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasTable('event_domain_outbox'));
        $this->assertTrue(Schema::hasColumn('event_domain_outbox', 'aggregate_stream'));
        $this->assertTrue(Schema::hasTable('event_notification_deliveries'));
        $this->assertTrue(Schema::hasTable('event_notification_outbox_replays'));
        $this->assertTrue(Schema::hasColumn('notification_queue', 'event_delivery_id'));
        $this->assertTrue(Schema::hasColumn('notification_queue', 'idempotency_key'));
    }

    public function test_direct_record_and_delivery_are_idempotent(): void
    {
        $organizer = $this->member();
        $recipient = $this->member();
        $eventId = $this->eventOwnedBy((int) $organizer->id);

        $first = $this->outbox->record(
            $this->testTenantId,
            $eventId,
            1,
            'event.created',
            "event:{$eventId}:created:v1",
            ['event_id' => $eventId],
            EventNotificationDeliveryMode::Direct,
        );
        $second = $this->outbox->record(
            $this->testTenantId,
            $eventId,
            1,
            'event.created',
            "event:{$eventId}:created:v1",
            ['event_id' => $eventId],
            EventNotificationDeliveryMode::Direct,
        );

        $this->assertSame((int) $first['id'], (int) $second['id']);
        $this->assertSame('direct', $first['status']);
        $this->assertNotNull($first['processed_at']);
        $this->assertSame(1, DB::table('event_domain_outbox')->where('event_id', $eventId)->count());

        $deliveryKey = EventDomainOutboxService::deliveryKey(
            $this->testTenantId,
            $eventId,
            'event.created',
            (int) $recipient->id,
            'email',
            1,
        );
        $deliveryA = $this->outbox->ensureDelivery((int) $first['id'], (int) $recipient->id, 'email', $deliveryKey);
        $deliveryB = $this->outbox->ensureDelivery((int) $first['id'], (int) $recipient->id, 'email', $deliveryKey);

        $this->assertSame((int) $deliveryA['id'], (int) $deliveryB['id']);
        $this->assertSame('direct', $deliveryA['status']);
        $this->assertSame(1, DB::table('event_notification_deliveries')->where('outbox_id', $first['id'])->count());
    }

    public function test_domain_transaction_rollback_removes_outbox_row(): void
    {
        $organizer = $this->member();
        $eventId = $this->eventOwnedBy((int) $organizer->id);

        try {
            DB::transaction(function () use ($eventId): void {
                $this->outbox->record(
                    $this->testTenantId,
                    $eventId,
                    1,
                    'event.updated',
                    "event:{$eventId}:updated:v1",
                    ['event_id' => $eventId],
                    EventNotificationDeliveryMode::OutboxAuthoritative,
                );
                throw new RuntimeException('Injected domain failure');
            });
        } catch (RuntimeException $e) {
            $this->assertSame('Injected domain failure', $e->getMessage());
        }

        $this->assertSame(0, DB::table('event_domain_outbox')
            ->where('idempotency_key', "event:{$eventId}:updated:v1")
            ->count());
    }

    public function test_shadow_rows_are_never_claimed(): void
    {
        $organizer = $this->member();
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        $row = $this->outbox->record(
            $this->testTenantId,
            $eventId,
            1,
            'event.updated',
            "event:{$eventId}:shadow:v1",
            ['event_id' => $eventId],
            EventNotificationDeliveryMode::ShadowOutbox,
        );
        Config::set('events.notification_delivery.consumer_enabled', true);

        $this->assertSame('shadow', $row['status']);
        $this->assertSame([], (new EventNotificationOutboxConsumer())->claimBatch());
    }

    public function test_authoritative_rows_are_claimed_in_aggregate_version_order(): void
    {
        $organizer = $this->member();
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        foreach ([1, 2] as $version) {
            $this->outbox->record(
                $this->testTenantId,
                $eventId,
                $version,
                'event.lifecycle.transitioned',
                "event:{$eventId}:updated:v{$version}",
                ['event_id' => $eventId, 'version' => $version],
                EventNotificationDeliveryMode::OutboxAuthoritative,
            );
        }
        Config::set('events.notification_delivery.consumer_enabled', true);
        $consumer = new EventNotificationOutboxConsumer();

        $firstBatch = $consumer->claimBatch(10);
        $this->assertCount(1, $firstBatch);
        $this->assertSame(1, (int) $firstBatch[0]['aggregate_version']);
        $this->assertTrue($consumer->markProcessed((int) $firstBatch[0]['id'], (string) $firstBatch[0]['claim_token']));

        $secondBatch = $consumer->claimBatch(10);
        $this->assertCount(1, $secondBatch);
        $this->assertSame(2, (int) $secondBatch[0]['aggregate_version']);
    }

    public function test_dead_letter_in_one_aggregate_stream_does_not_block_another_stream(): void
    {
        $organizer = $this->member();
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        $staff = $this->outbox->record(
            $this->testTenantId,
            $eventId,
            1,
            'event.staff_role.granted',
            "event:{$eventId}:staff:7:v1",
            ['event_id' => $eventId, 'assignment_id' => 7, 'user_id' => (int) $organizer->id],
            EventNotificationDeliveryMode::OutboxAuthoritative,
        );
        DB::table('event_domain_outbox')->where('id', $staff['id'])->update([
            'status' => 'dead_letter',
            'dead_lettered_at' => now(),
        ]);
        $lifecycle = $this->outbox->record(
            $this->testTenantId,
            $eventId,
            2,
            'event.lifecycle.transitioned',
            "event:{$eventId}:lifecycle:v2",
            ['event_id' => $eventId, 'lifecycle_version' => 2],
            EventNotificationDeliveryMode::OutboxAuthoritative,
        );
        Config::set('events.notification_delivery.consumer_enabled', true);

        $claimed = (new EventNotificationOutboxConsumer())->claimBatch(10);

        $this->assertCount(1, $claimed);
        $this->assertSame((int) $lifecycle['id'], (int) $claimed[0]['id']);
        $this->assertNotSame($staff['aggregate_stream'], $lifecycle['aggregate_stream']);
    }

    public function test_legacy_event_stream_remains_a_global_barrier_during_expand_rollout(): void
    {
        $organizer = $this->member();
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        DB::table('event_domain_outbox')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'aggregate_stream' => 'event',
            'aggregate_version' => 1,
            'action' => 'event.lifecycle.transitioned',
            'idempotency_key' => "event:{$eventId}:legacy:v1",
            'production_mode' => 'outbox_authoritative',
            'status' => 'dead_letter',
            'payload' => json_encode(['event_id' => $eventId], JSON_THROW_ON_ERROR),
            'dead_lettered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->outbox->record(
            $this->testTenantId,
            $eventId,
            2,
            'event.lifecycle.transitioned',
            "event:{$eventId}:lifecycle:v2",
            ['event_id' => $eventId, 'lifecycle_version' => 2],
            EventNotificationDeliveryMode::OutboxAuthoritative,
        );
        Config::set('events.notification_delivery.consumer_enabled', true);

        $this->assertSame([], (new EventNotificationOutboxConsumer())->claimBatch(10));
    }

    public function test_retry_accepts_a_matching_row_written_before_stream_expansion(): void
    {
        $organizer = $this->member();
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        $key = "event:{$eventId}:lifecycle:v1";
        DB::table('event_domain_outbox')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'aggregate_stream' => 'event',
            'aggregate_version' => 1,
            'action' => 'event.lifecycle.transitioned',
            'idempotency_key' => $key,
            'production_mode' => 'direct',
            'status' => 'direct',
            'payload' => json_encode(['event_id' => $eventId], JSON_THROW_ON_ERROR),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = $this->outbox->record(
            $this->testTenantId,
            $eventId,
            1,
            'event.lifecycle.transitioned',
            $key,
            ['event_id' => $eventId],
            EventNotificationDeliveryMode::Direct,
        );

        $this->assertSame('event', $row['aggregate_stream']);
        $this->assertSame(1, DB::table('event_domain_outbox')->where('idempotency_key', $key)->count());
    }

    public function test_failed_claim_dead_letters_at_the_configured_attempt_limit(): void
    {
        $organizer = $this->member();
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        $this->outbox->record(
            $this->testTenantId,
            $eventId,
            1,
            'event.lifecycle.transitioned',
            "event:{$eventId}:cancelled:v1",
            ['event_id' => $eventId],
            EventNotificationDeliveryMode::OutboxAuthoritative,
        );
        Config::set('events.notification_delivery.consumer_enabled', true);
        Config::set('events.notification_delivery.max_attempts', 1);
        $consumer = new EventNotificationOutboxConsumer();
        $claimed = $consumer->claimBatch(1)[0];

        $this->assertTrue($consumer->markFailed((int) $claimed['id'], (string) $claimed['claim_token'], 'Injected poison row'));
        $this->assertSame('dead_letter', DB::table('event_domain_outbox')->where('id', $claimed['id'])->value('status'));
        $this->assertNotNull(DB::table('event_domain_outbox')->where('id', $claimed['id'])->value('dead_lettered_at'));
        $this->assertSame([], $consumer->claimBatch(1));
    }

    public function test_invalid_delivery_mode_falls_back_to_direct(): void
    {
        Config::set('events.notification_delivery.mode', 'invalid-mode');

        $this->assertSame(EventNotificationDeliveryMode::Direct, EventNotificationDeliveryModeResolver::resolve());
    }
}
