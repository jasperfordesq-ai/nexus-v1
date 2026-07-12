<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Exceptions\EventTicketingException;
use App\Services\EventTicketEntitlementService;
use App\Services\EventTicketReconciliationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventTicketingFixtures;
use Tests\Laravel\TestCase;

final class EventTicketReconciliationServiceTest extends TestCase
{
    use DatabaseTransactions;
    use BuildsEventTicketingFixtures;

    public function test_reconciliation_is_policy_gated_read_only_and_reports_durable_inventory_evidence(): void
    {
        $owner = $this->ticketUser();
        $firstMember = $this->ticketUser();
        $secondMember = $this->ticketUser();
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        $firstRegistration = $this->ticketRegistration($eventId, (int) $firstMember->id);
        $secondRegistration = $this->ticketRegistration($eventId, (int) $secondMember->id);
        $ticket = $this->activeTicketType($eventId, $owner, $start, [
            'allocation_limit' => 5,
            'per_member_limit' => 1,
        ]);
        $entitlements = new EventTicketEntitlementService();
        $first = $entitlements->allocateSelf(
            $eventId,
            (int) $ticket->id,
            $firstRegistration,
            $firstMember,
            1,
            'reconciliation-first-allocation',
        );
        $entitlements->allocateSelf(
            $eventId,
            (int) $ticket->id,
            $secondRegistration,
            $secondMember,
            1,
            'reconciliation-second-allocation',
        );
        $entitlements->cancel(
            $eventId,
            (int) $first['entitlement']->id,
            $owner,
            1,
            'Organizer cancelled one ticket.',
            'reconciliation-cancellation',
        );

        $service = new EventTicketReconciliationService();
        $this->assertReason(
            'event_ticket_reconciliation_denied',
            fn () => $service->report($eventId, $firstMember),
        );
        $before = $this->ticketEvidenceSnapshot($eventId);
        $report = $service->report($eventId, $owner);

        self::assertTrue($report['read_only']);
        self::assertSame($eventId, $report['event_id']);
        self::assertCount(1, $report['ticket_types']);
        self::assertSame([
            'ticket_type_id' => (int) $ticket->id,
            'kind' => 'free',
            'status' => 'active',
            'allocation_limit' => 5,
            'confirmed_units' => 1,
            'cancelled_units' => 1,
            'confirmed_entitlements' => 1,
            'cancelled_entitlements' => 1,
            'registration_mismatches' => 0,
            'price_snapshot_violations' => 0,
            'inventory_delta' => 1,
            'latest_inventory_after' => 1,
            'allocation_overrun' => false,
            'inventory_mismatch' => false,
        ], $report['ticket_types'][0]);
        self::assertSame($before, $this->ticketEvidenceSnapshot($eventId));
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function ticketEvidenceSnapshot(int $eventId): array
    {
        $snapshot = [];
        foreach ([
            'event_ticket_types',
            'event_ticket_type_history',
            'event_ticket_entitlements',
            'event_ticket_entitlement_history',
            'event_ticket_inventory_history',
        ] as $table) {
            $snapshot[$table] = DB::table($table)
                ->where('tenant_id', $this->testTenantId)
                ->where('event_id', $eventId)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();
        }

        return $snapshot;
    }

    /** @param callable():mixed $operation */
    private function assertReason(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail("Expected {$reason}.");
        } catch (EventTicketingException $exception) {
            self::assertSame($reason, $exception->getMessage());
        }
    }
}
