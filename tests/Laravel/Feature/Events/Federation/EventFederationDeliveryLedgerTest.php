<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events\Federation;

use App\Models\Event;
use App\Models\User;
use App\Services\EventFederationDeliveryLedger;
use App\Services\EventFederationPayloadBuilder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\Laravel\TestCase;

final class EventFederationDeliveryLedgerTest extends TestCase
{
    use DatabaseTransactions;

    private EventFederationDeliveryLedger $ledger;
    private EventFederationPayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = new EventFederationDeliveryLedger();
        $this->builder = new EventFederationPayloadBuilder();
    }

    public function test_enqueue_is_idempotent_per_partner_and_isolates_partner_streams(): void
    {
        $event = $this->event();
        $firstPartner = $this->partner($this->testTenantId, 'first');
        $secondPartner = $this->partner($this->testTenantId, 'second');
        $payload = $this->builder->build($event);

        $first = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $firstPartner,
            $payload,
        );
        $replay = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $firstPartner,
            $payload,
        );
        $otherPartner = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $secondPartner,
            $payload,
        );

        self::assertSame((int) $first['id'], (int) $replay['id']);
        self::assertNotSame((int) $first['id'], (int) $otherPartner['id']);
        self::assertSame(2, DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->count());
        self::assertNotSame($first['idempotency_key'], $otherPartner['idempotency_key']);
    }

    public function test_same_version_with_different_payload_is_an_idempotency_conflict(): void
    {
        $event = $this->event();
        $partner = $this->partner($this->testTenantId, 'conflict');
        $payload = $this->builder->build($event);
        $this->ledger->enqueue($this->testTenantId, (int) $event->id, $partner, $payload);
        $payload['title'] = 'Conflicting title';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('event_federation_delivery_idempotency_conflict');
        $this->ledger->enqueue($this->testTenantId, (int) $event->id, $partner, $payload);
    }

    public function test_partner_from_another_tenant_cannot_receive_or_read_local_fact(): void
    {
        $event = $this->event();
        $foreignPartner = $this->partner(999, 'foreign');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('event_federation_delivery_partner_unavailable');
        $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $foreignPartner,
            $this->builder->build($event),
        );
    }

    public function test_claims_are_fenced_and_later_versions_cannot_overtake(): void
    {
        $event = $this->event();
        $partner = $this->partner($this->testTenantId, 'ordering');
        $firstPayload = $this->builder->build($event);
        $event->setAttribute('lifecycle_version', 9);
        $event->setAttribute('calendar_sequence', 14);
        $event->setAttribute('federation_version', 9);
        $event->setAttribute('title', 'Updated title');
        $secondPayload = $this->builder->build($event);
        // Simulate queued domain facts arriving out of order: version ordering,
        // not insertion id, must decide which delivery is claimable first.
        $second = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $partner,
            $secondPayload,
        );
        $first = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $partner,
            $firstPayload,
        );

        $claimed = $this->ledger->claimBatch(10, $this->testTenantId, $partner);
        self::assertCount(1, $claimed);
        self::assertSame((int) $first['id'], (int) $claimed[0]['id']);
        self::assertSame([], $this->ledger->claimBatch(10, $this->testTenantId, $partner));
        self::assertFalse($this->ledger->markDelivered(
            $this->testTenantId,
            (int) $claimed[0]['id'],
            'wrong-claim-token',
        ));
        self::assertTrue($this->ledger->markDelivered(
            $this->testTenantId,
            (int) $claimed[0]['id'],
            (string) $claimed[0]['claim_token'],
        ));

        $next = $this->ledger->claimBatch(10, $this->testTenantId, $partner);
        self::assertCount(1, $next);
        self::assertSame((int) $second['id'], (int) $next[0]['id']);
    }

    public function test_failures_retry_with_redacted_evidence_and_dead_letter_at_bound(): void
    {
        $event = $this->event();
        $partner = $this->partner($this->testTenantId, 'retry');
        $delivery = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $partner,
            $this->builder->build($event),
        );

        for ($attempt = 1; $attempt <= EventFederationDeliveryLedger::MAX_ATTEMPTS; $attempt++) {
            $claim = $this->ledger->claimBatch(1, $this->testTenantId, $partner);
            self::assertCount(1, $claim);
            self::assertTrue($this->ledger->markFailed(
                $this->testTenantId,
                (int) $delivery['id'],
                (string) $claim[0]['claim_token'],
                'http 503 / temporary',
                'Bearer raw-secret for person@example.test token=another-secret',
            ));
            if ($attempt < EventFederationDeliveryLedger::MAX_ATTEMPTS) {
                DB::table('event_federation_deliveries')
                    ->where('id', $delivery['id'])
                    ->update(['next_attempt_at' => now()]);
            }
        }

        $row = DB::table('event_federation_deliveries')->where('id', $delivery['id'])->first();
        self::assertNotNull($row);
        self::assertSame('dead_letter', $row->status);
        self::assertSame(EventFederationDeliveryLedger::MAX_ATTEMPTS, (int) $row->attempts);
        self::assertNotNull($row->dead_lettered_at);
        self::assertSame('HTTP_503___TEMPORARY', $row->last_error_code);
        self::assertStringNotContainsString('raw-secret', (string) $row->last_error);
        self::assertStringNotContainsString('another-secret', (string) $row->last_error);
        self::assertStringNotContainsString('person@example.test', (string) $row->last_error);
        self::assertSame([], $this->ledger->claimBatch(1, $this->testTenantId, $partner));
    }

    public function test_coordinate_and_visibility_changes_are_rejected_until_federation_version_advances(): void
    {
        $event = $this->event();
        $partner = $this->partner($this->testTenantId, 'visible-version');
        $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $partner,
            $this->builder->build($event),
        );

        $event->setAttribute('latitude', 52.5);
        try {
            $this->ledger->enqueue(
                $this->testTenantId,
                (int) $event->id,
                $partner,
                $this->builder->build($event),
            );
            self::fail('Coordinate mutation reused the federation version.');
        } catch (LogicException $exception) {
            self::assertSame('event_federation_delivery_idempotency_conflict', $exception->getMessage());
        }

        $event->setAttribute('federation_version', 9);
        $coordinateDelivery = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $partner,
            $this->builder->build($event),
        );
        self::assertSame(9, (int) $coordinateDelivery['event_aggregate_version']);

        $event->setAttribute('federated_visibility', 'none');
        try {
            $this->ledger->enqueue(
                $this->testTenantId,
                (int) $event->id,
                $partner,
                $this->builder->build($event),
            );
            self::fail('Visibility withdrawal reused the federation version.');
        } catch (LogicException $exception) {
            self::assertSame('event_federation_delivery_idempotency_conflict', $exception->getMessage());
        }

        $event->setAttribute('federation_version', 10);
        $withdrawal = $this->ledger->enqueue(
            $this->testTenantId,
            (int) $event->id,
            $partner,
            $this->builder->build($event),
        );
        self::assertSame('tombstone', $withdrawal['action']);
        self::assertSame(10, (int) $withdrawal['event_aggregate_version']);
    }

    public function test_retraction_remains_admissible_after_opt_out_or_suspension_with_prior_evidence(): void
    {
        foreach (['active-opt-out' => 'active', 'suspended' => 'suspended'] as $suffix => $status) {
            $event = $this->event();
            $partner = $this->partner($this->testTenantId, $suffix);
            $this->ledger->enqueue(
                $this->testTenantId,
                (int) $event->id,
                $partner,
                $this->builder->build($event),
            );
            DB::table('federation_external_partners')->where('id', $partner)->update([
                'status' => $status,
                'allow_events' => false,
            ]);

            $tombstone = $this->builder->buildDeletion(
                $this->testTenantId,
                (int) $event->id,
                9,
                14,
                now(),
            );
            $delivery = $this->ledger->enqueue(
                $this->testTenantId,
                (int) $event->id,
                $partner,
                $tombstone,
            );
            self::assertSame('tombstone', $delivery['action']);
        }
    }

    public function test_opted_out_partner_without_prior_evidence_cannot_receive_arbitrary_tombstone(): void
    {
        $event = $this->event();
        $partner = $this->partner($this->testTenantId, 'no-evidence');
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
            $this->ledger->enqueue(
                $this->testTenantId,
                (int) $event->id,
                $partner,
                $this->builder->buildDeletion(
                    $this->testTenantId,
                    (int) $event->id,
                    9,
                    14,
                    now(),
                ),
            );
            self::fail('Opted-out partner without evidence accepted a tombstone.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('event_federation_delivery_retraction_evidence_missing', $exception->getMessage());
        }
        self::assertTrue($partnerLockObserved);
        self::assertGreaterThan($baselineTransactionLevel, $transactionLevel);
        self::assertSame(0, DB::table('event_federation_deliveries')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('external_partner_id', $partner)
            ->count());
    }

    public function test_stale_claim_release_locks_only_the_requested_bounded_batch(): void
    {
        $partner = $this->partner($this->testTenantId, 'stale-batch');
        foreach ([$this->event(), $this->event()] as $event) {
            $this->ledger->enqueue(
                $this->testTenantId,
                (int) $event->id,
                $partner,
                $this->builder->build($event),
            );
        }
        $claimed = $this->ledger->claimBatch(10, $this->testTenantId, $partner);
        self::assertCount(2, $claimed);
        DB::table('event_federation_deliveries')
            ->whereIn('id', array_column($claimed, 'id'))
            ->update(['claimed_at' => now()->subMinutes(EventFederationDeliveryLedger::STALE_CLAIM_MINUTES + 1)]);

        self::assertSame(1, $this->ledger->releaseStaleClaims($this->testTenantId, 1));
        self::assertSame(1, DB::table('event_federation_deliveries')
            ->whereIn('id', array_column($claimed, 'id'))
            ->where('status', 'retry')
            ->count());
        self::assertSame(1, DB::table('event_federation_deliveries')
            ->whereIn('id', array_column($claimed, 'id'))
            ->where('status', 'processing')
            ->count());
    }

    private function event(): Event
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        return Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $organizer->id,
            'title' => 'Federated ledger event',
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHours(2),
            'timezone' => 'UTC',
            'all_day' => false,
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
            'name' => 'Event federation ' . $suffix,
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
