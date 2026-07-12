<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events\Federation;

use App\Contracts\EventFederationTransport;
use App\Core\TenantContext;
use App\Models\Event;
use App\Models\User;
use App\Services\EventFederationDeliveryConsumer;
use App\Services\EventFederationDeliveryLedger;
use App\Services\EventFederationDiagnostics;
use App\Services\EventFederationPayloadBuilder;
use App\Support\Events\EventFederationReceiptContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class EventFederationPhaseBDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    private EventFederationDeliveryLedger $ledger;
    private EventFederationPayloadBuilder $payloads;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        $this->enableEventFederation();
        $this->ledger = new EventFederationDeliveryLedger();
        $this->payloads = new EventFederationPayloadBuilder();
    }

    public function test_consumer_delivers_valid_receipt_and_marks_only_federation_ledger(): void
    {
        $event = $this->event();
        $partnerId = $this->partner('accepted');
        $delivery = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $partnerId,
            $this->payloads->build($event),
        );
        $transport = new RecordingEventFederationTransport('accepted');
        $consumer = new EventFederationDeliveryConsumer($this->ledger, $transport);
        $notificationDeliveryCount = DB::table('event_notification_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->count();

        $summary = $consumer->processBatch(10, $this->testTenantId, $partnerId);

        self::assertSame(1, $summary['claimed']);
        self::assertSame(1, $summary['delivered']);
        self::assertCount(1, $transport->calls);
        self::assertSame('delivered', DB::table('event_federation_deliveries')
            ->where('id', $delivery['id'])
            ->value('status'));
        self::assertNull(DB::table('event_federation_deliveries')
            ->where('id', $delivery['id'])
            ->value('claim_token'));
        self::assertSame($notificationDeliveryCount, DB::table('event_notification_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->count());
    }

    public function test_remote_conflict_retries_without_acknowledging_delivery(): void
    {
        $event = $this->event();
        $partnerId = $this->partner('conflict');
        $delivery = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $partnerId,
            $this->payloads->build($event),
        );
        $consumer = new EventFederationDeliveryConsumer(
            $this->ledger,
            new RecordingEventFederationTransport('conflict'),
        );

        $summary = $consumer->processBatch(1, $this->testTenantId, $partnerId);
        $stored = DB::table('event_federation_deliveries')->where('id', $delivery['id'])->first();

        self::assertSame(1, $summary['retrying']);
        self::assertNotNull($stored);
        self::assertSame('retry', $stored->status);
        self::assertSame('REMOTE_VERSION_CONFLICT', $stored->last_error_code);
        self::assertNull($stored->claim_token);
        self::assertNotNull($stored->next_attempt_at);
        self::assertNull($stored->delivered_at);
    }

    public function test_consumer_revalidates_stored_payload_before_transport(): void
    {
        $event = $this->event();
        $partnerId = $this->partner('tamper');
        $delivery = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $partnerId,
            $this->payloads->build($event),
        );
        DB::table('event_federation_deliveries')->where('id', $delivery['id'])->update([
            'payload' => json_encode([
                'registration_roster' => [['email' => 'private@example.test']],
            ], JSON_THROW_ON_ERROR),
        ]);
        $transport = new RecordingEventFederationTransport('accepted');
        $consumer = new EventFederationDeliveryConsumer($this->ledger, $transport);

        $summary = $consumer->processBatch(1, $this->testTenantId, $partnerId);
        $stored = DB::table('event_federation_deliveries')->where('id', $delivery['id'])->first();

        self::assertSame(1, $summary['retrying']);
        self::assertCount(0, $transport->calls);
        self::assertNotNull($stored);
        self::assertSame('retry', $stored->status);
        self::assertSame('DELIVERY_EXCEPTION', $stored->last_error_code);
        self::assertStringNotContainsString('private@example.test', (string) $stored->last_error);
    }

    public function test_diagnostics_are_payload_free_and_show_retry_health(): void
    {
        $event = $this->event();
        $partnerId = $this->partner('diagnostics');
        $delivery = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $partnerId,
            $this->payloads->build($event),
        );
        DB::table('event_federation_deliveries')->where('id', $delivery['id'])->update([
            'status' => 'retry',
            'attempts' => 2,
            'last_error_code' => 'REMOTE_HTTP_503',
            'last_error' => 'Bearer private-token private@example.test',
            'next_attempt_at' => now()->addMinute(),
        ]);

        $status = (new EventFederationDiagnostics())->eventStatus($event->fresh() ?? $event);
        $encoded = json_encode($status, JSON_THROW_ON_ERROR);

        self::assertSame('delivering', $status['health']);
        self::assertSame(1, $status['counts']['retry']);
        self::assertSame('REMOTE_HTTP_503', $status['partners'][0]['error_code']);
        self::assertStringNotContainsString('private-token', $encoded);
        self::assertStringNotContainsString('private@example.test', $encoded);
        self::assertStringNotContainsString('payload_hash', $encoded);
        self::assertStringNotContainsString('claim_token', $encoded);
    }

    public function test_disabled_events_module_drains_tombstone_but_leaves_upsert_unclaimed(): void
    {
        $pendingEvent = $this->event();
        $retractedEvent = $this->event();
        $partnerId = $this->partner('module-disabled');
        $pending = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $pendingEvent->id,
            $partnerId,
            $this->payloads->build($pendingEvent),
        );
        $previous = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $retractedEvent->id,
            $partnerId,
            $this->payloads->build($retractedEvent),
        );
        DB::table('event_federation_deliveries')->where('id', $previous['id'])->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        $tombstone = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $retractedEvent->id,
            $partnerId,
            $this->payloads->buildDeletion(
                $this->testTenantId,
                (int) $retractedEvent->id,
                9,
                4,
                now(),
            ),
        );
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => false], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $transport = new RecordingEventFederationTransport('accepted');
        $consumer = new EventFederationDeliveryConsumer($this->ledger, $transport);

        $summary = $consumer->processBatch(10, $this->testTenantId, $partnerId);

        self::assertSame(1, $summary['claimed']);
        self::assertSame(1, $summary['delivered']);
        self::assertSame('pending', DB::table('event_federation_deliveries')
            ->where('id', $pending['id'])
            ->value('status'));
        self::assertSame(0, (int) DB::table('event_federation_deliveries')
            ->where('id', $pending['id'])
            ->value('attempts'));
        self::assertSame('delivered', DB::table('event_federation_deliveries')
            ->where('id', $tombstone['id'])
            ->value('status'));
        self::assertSame('tombstone', $transport->calls[0]['payload']['action']);
    }

    private function event(): Event
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        return Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $organizer->id,
            'title' => 'Phase B delivery event',
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHours(2),
            'timezone' => 'UTC',
            'all_day' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'federated_visibility' => 'listed',
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
            'name' => 'Phase B delivery partner ' . $suffix,
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

final class RecordingEventFederationTransport implements EventFederationTransport
{
    /** @var list<array{tenant_id:int,partner_id:int,payload:array<string,mixed>}> */
    public array $calls = [];

    public function __construct(private readonly string $decision) {}

    public function deliver(int $tenantId, int $externalPartnerId, array $payload): array
    {
        $this->calls[] = [
            'tenant_id' => $tenantId,
            'partner_id' => $externalPartnerId,
            'payload' => $payload,
        ];
        $receipt = [
            'contract' => EventFederationReceiptContract::SCHEMA,
            'contract_version' => EventFederationReceiptContract::SCHEMA_VERSION,
            'decision' => $this->decision,
            'action' => (string) $payload['action'],
            'event_aggregate_version' => (int) $payload['event_aggregate_version'],
            'event_calendar_version' => (int) $payload['event_calendar_version'],
            'received_at' => now()->utc()->toIso8601String(),
        ];

        return ['success' => true, 'receipt' => $receipt];
    }
}
