<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events\Federation;

use App\Enums\EventFederationInboundDecision;
use App\Models\Event;
use App\Models\User;
use App\Services\EventFederationInboundProjectionService;
use App\Services\EventFederationPayloadBuilder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Laravel\TestCase;

final class EventFederationInboundProjectionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EventFederationInboundProjectionService $service;
    private EventFederationPayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventFederationInboundProjectionService();
        $this->builder = new EventFederationPayloadBuilder();
    }

    public function test_newer_payload_is_accepted_and_identical_replay_is_idempotent(): void
    {
        $partner = $this->partner($this->testTenantId, 'replay');
        $payload = $this->builder->build($this->event());

        $accepted = $this->service->ingest($this->testTenantId, $partner, $payload);
        $replay = $this->service->ingest($this->testTenantId, $partner, array_reverse($payload, true));

        self::assertSame(EventFederationInboundDecision::Accepted, $accepted->decision);
        self::assertSame(EventFederationInboundDecision::Replay, $replay->decision);
        self::assertSame($accepted->projectionId, $replay->projectionId);
        self::assertSame(1, DB::table('federation_events')
            ->where('tenant_id', $this->testTenantId)
            ->where('external_partner_id', $partner)
            ->where('external_id', $payload['external_id'])
            ->count());
        $row = DB::table('federation_events')->where('id', $accepted->projectionId)->first();
        self::assertNotNull($row);
        self::assertSame('upsert', $row->source_action);
        self::assertSame(8, (int) $row->source_aggregate_version);
        self::assertSame(13, (int) $row->source_calendar_version);
        self::assertSame(1, (int) $row->replay_count);
        self::assertNotNull($row->last_replayed_at);
        $metadata = json_decode((string) $row->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('UTC', $metadata['timezone']);
        self::assertFalse($metadata['all_day']);
        self::assertSame(53.3, $metadata['latitude']);
        self::assertSame(-6.2, $metadata['longitude']);
        self::assertFalse($metadata['is_online']);
        self::assertSame($payload['created_at'], $metadata['created_at']);
        self::assertSame($payload['updated_at'], $metadata['updated_at']);
    }

    public function test_same_version_with_different_payload_is_conflict_and_does_not_overwrite(): void
    {
        $partner = $this->partner($this->testTenantId, 'same-version');
        $payload = $this->builder->build($this->event());
        $accepted = $this->service->ingest($this->testTenantId, $partner, $payload);
        $conflicting = $payload;
        $conflicting['title'] = 'Conflicting same-version title';

        $result = $this->service->ingest($this->testTenantId, $partner, $conflicting);

        self::assertSame(EventFederationInboundDecision::Conflict, $result->decision);
        $row = DB::table('federation_events')->where('id', $accepted->projectionId)->first();
        self::assertNotNull($row);
        self::assertSame('Federated inbound event', $row->title);
        self::assertSame(1, (int) $row->conflict_count);
        self::assertNotNull($row->last_conflict_at);
        self::assertSame($result->payloadHash, $row->last_conflict_hash);
    }

    public function test_stale_and_crossed_versions_are_rejected_without_projection_mutation(): void
    {
        $partner = $this->partner($this->testTenantId, 'versions');
        $payload = $this->builder->build($this->event());
        $accepted = $this->service->ingest($this->testTenantId, $partner, $payload);

        $stale = $payload;
        $stale['event_aggregate_version'] = 7;
        $stale['event_calendar_version'] = 12;
        $stale['title'] = 'Stale title';
        $crossed = $payload;
        $crossed['event_aggregate_version'] = 9;
        $crossed['event_calendar_version'] = 12;
        $crossed['title'] = 'Crossed title';

        $staleResult = $this->service->ingest($this->testTenantId, $partner, $stale);
        $crossedResult = $this->service->ingest($this->testTenantId, $partner, $crossed);

        self::assertSame(EventFederationInboundDecision::Stale, $staleResult->decision);
        self::assertSame(EventFederationInboundDecision::Conflict, $crossedResult->decision);
        $row = DB::table('federation_events')->where('id', $accepted->projectionId)->first();
        self::assertNotNull($row);
        self::assertSame('Federated inbound event', $row->title);
        self::assertSame(8, (int) $row->source_aggregate_version);
        self::assertSame(13, (int) $row->source_calendar_version);
        self::assertSame(1, (int) $row->stale_count);
        self::assertSame(1, (int) $row->conflict_count);
    }

    public function test_tombstone_prevents_stale_resurrection_and_retains_withdrawal_evidence(): void
    {
        $partner = $this->partner($this->testTenantId, 'tombstone');
        $event = $this->event();
        $upsert = $this->builder->build($event);
        $accepted = $this->service->ingest($this->testTenantId, $partner, $upsert);
        $tombstone = $this->builder->buildDeletion(
            $this->testTenantId,
            (int) $event->id,
            9,
            14,
            now(),
        );
        DB::table('federation_external_partners')->where('id', $partner)->update(['allow_events' => false]);

        $withdrawn = $this->service->ingest($this->testTenantId, $partner, $tombstone);
        // A disabled partner cannot send any upsert. Restore its explicit event
        // permission before exercising the separate stale-version boundary.
        DB::table('federation_external_partners')->where('id', $partner)->update(['allow_events' => true]);
        $delayed = $this->service->ingest($this->testTenantId, $partner, $upsert);
        $afterDelayed = DB::table('federation_events')->where('id', $accepted->projectionId)->first();

        self::assertSame(EventFederationInboundDecision::Accepted, $withdrawn->decision);
        self::assertSame(EventFederationInboundDecision::Stale, $delayed->decision);
        self::assertNotNull($afterDelayed);
        self::assertSame(1, (int) $afterDelayed->is_tombstone);
        self::assertSame('tombstone', $afterDelayed->source_action);
        self::assertSame('deleted', $afterDelayed->tombstone_reason);
        self::assertSame('urn:nexus:event:' . $this->testTenantId . ':' . $event->id, $afterDelayed->title);
        self::assertNull($afterDelayed->location);
        self::assertNotNull($afterDelayed->tombstoned_at);
        $tombstoneMetadata = json_decode(
            (string) $afterDelayed->metadata,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        foreach (['timezone', 'all_day', 'latitude', 'longitude', 'is_online', 'created_at', 'updated_at'] as $field) {
            self::assertArrayNotHasKey($field, $tombstoneMetadata);
        }

        $event->setAttribute('lifecycle_version', 10);
        $event->setAttribute('calendar_sequence', 15);
        $event->setAttribute('federation_version', 10);
        $event->setAttribute('title', 'Legitimately republished event');
        $newerUpsert = $this->builder->build($event);
        $republished = $this->service->ingest($this->testTenantId, $partner, $newerUpsert);
        $afterRepublish = DB::table('federation_events')->where('id', $accepted->projectionId)->first();

        self::assertSame(EventFederationInboundDecision::Accepted, $republished->decision);
        self::assertNotNull($afterRepublish);
        self::assertSame(0, (int) $afterRepublish->is_tombstone);
        self::assertSame('Legitimately republished event', $afterRepublish->title);
        self::assertNull($afterRepublish->tombstoned_at);
        self::assertNull($afterRepublish->tombstone_reason);
    }

    public function test_opted_out_partner_may_only_retract_an_existing_projection(): void
    {
        $event = $this->event();
        $payload = $this->builder->build($event);
        $partner = $this->partner($this->testTenantId, 'opt-out-evidence');
        $this->service->ingest($this->testTenantId, $partner, $payload);
        DB::table('federation_external_partners')->where('id', $partner)->update(['allow_events' => false]);

        $result = $this->service->ingest(
            $this->testTenantId,
            $partner,
            $this->builder->buildDeletion(
                $this->testTenantId,
                (int) $event->id,
                9,
                14,
                now(),
            ),
        );

        self::assertSame(EventFederationInboundDecision::Accepted, $result->decision);
        self::assertSame(1, (int) DB::table('federation_events')
            ->where('id', $result->projectionId)
            ->value('is_tombstone'));
    }

    public function test_opted_out_partner_without_projection_cannot_create_tombstone(): void
    {
        $event = $this->event();
        $partner = $this->partner($this->testTenantId, 'opt-out-no-evidence');
        DB::table('federation_external_partners')->where('id', $partner)->update(['allow_events' => false]);
        $partnerLockObserved = false;
        $transactionLevel = 0;
        $baselineTransactionLevel = DB::connection()->transactionLevel();
        DB::listen(function (QueryExecuted $query) use (&$partnerLockObserved, &$transactionLevel): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'federation_external_partners') && str_contains($sql, 'for update')) {
                $partnerLockObserved = true;
                $transactionLevel = DB::connection()->transactionLevel();
            }
        });

        try {
            $this->service->ingest(
                $this->testTenantId,
                $partner,
                $this->builder->buildDeletion(
                    $this->testTenantId,
                    (int) $event->id,
                    9,
                    14,
                    now(),
                ),
            );
            self::fail('Opted-out partner without projection accepted a tombstone.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('event_federation_inbound_retraction_evidence_missing', $exception->getMessage());
        }
        self::assertTrue($partnerLockObserved);
        self::assertGreaterThan($baselineTransactionLevel, $transactionLevel);
        self::assertDatabaseMissing('federation_events', [
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => $partner,
            'external_id' => (string) $event->id,
        ]);
    }

    public function test_suspended_partner_cannot_use_normal_inbound_retraction_path_even_with_projection(): void
    {
        $event = $this->event();
        $partner = $this->partner($this->testTenantId, 'suspended');
        $this->service->ingest($this->testTenantId, $partner, $this->builder->build($event));
        DB::table('federation_external_partners')->where('id', $partner)->update([
            'status' => 'suspended',
            'allow_events' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('event_federation_inbound_partner_unavailable');
        $this->service->ingest(
            $this->testTenantId,
            $partner,
            $this->builder->buildDeletion(
                $this->testTenantId,
                (int) $event->id,
                9,
                14,
                now(),
            ),
        );
    }

    public function test_same_external_identity_is_isolated_by_partner_and_tenant(): void
    {
        $event = $this->event();
        $payload = $this->builder->build($event);
        $firstPartner = $this->partner($this->testTenantId, 'isolation-a');
        $secondPartner = $this->partner($this->testTenantId, 'isolation-b');

        $first = $this->service->ingest($this->testTenantId, $firstPartner, $payload);
        $second = $this->service->ingest($this->testTenantId, $secondPartner, $payload);

        self::assertNotSame($first->projectionId, $second->projectionId);
        self::assertSame(2, DB::table('federation_events')
            ->where('tenant_id', $this->testTenantId)
            ->where('external_id', $payload['external_id'])
            ->count());

        $foreignPartner = $this->partner(999, 'isolation-foreign');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('event_federation_inbound_partner_unavailable');
        $this->service->ingest($this->testTenantId, $foreignPartner, $payload);
    }

    private function event(): Event
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        return Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $organizer->id,
            'title' => 'Federated inbound event',
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(5)->addHours(2),
            'timezone' => 'UTC',
            'all_day' => false,
            'location' => 'Public library',
            'latitude' => 53.3,
            'longitude' => -6.2,
            'is_online' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'federated_visibility' => 'listed',
            'lifecycle_version' => 8,
            'calendar_sequence' => 13,
            'federation_version' => 8,
            'is_recurring_template' => false,
        ]);
    }

    private function partner(int $tenantId, string $suffix): int
    {
        return (int) DB::table('federation_external_partners')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Inbound event federation ' . $suffix,
            'base_url' => 'https://' . $suffix . '-' . uniqid() . '.example.test',
            'api_path' => '/api/v2/federation',
            'auth_method' => 'api_key',
            'status' => 'active',
            'allow_events' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
