<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Exceptions\EventTicketingException;
use App\Services\EventTicketEntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventTicketingFixtures;
use Tests\Laravel\TestCase;

final class EventTicketEntitlementServiceTest extends TestCase
{
    use DatabaseTransactions;
    use BuildsEventTicketingFixtures;

    /** @var list<string> */
    private const UNRELATED_TABLES = [
        'event_registrations',
        'event_waitlist_entries',
        'event_attendance_activity',
        'event_invitations',
        'event_registration_form_answers',
        'transactions',
        'notifications',
        'event_federation_deliveries',
    ];

    public function test_free_allocation_and_cancellation_are_idempotent_exactly_once_and_isolated(): void
    {
        $owner = $this->ticketUser();
        $member = $this->ticketUser();
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        $registrationId = $this->ticketRegistration($eventId, (int) $member->id);
        $ticket = $this->activeTicketType($eventId, $owner, $start, [
            'allocation_limit' => 4,
            'per_member_limit' => 2,
        ]);
        $service = new EventTicketEntitlementService();
        $unrelatedBefore = $this->unrelatedSnapshot(
            $this->testTenantId,
            $eventId,
            [(int) $owner->id, (int) $member->id],
        );

        $allocated = $service->allocateSelf(
            $eventId,
            (int) $ticket->id,
            $registrationId,
            $member,
            2,
            'free-entitlement-allocate',
        );
        $allocationReplay = $service->allocateSelf(
            $eventId,
            (int) $ticket->id,
            $registrationId,
            $member,
            2,
            'free-entitlement-allocate',
        );

        self::assertTrue($allocated['changed']);
        self::assertFalse($allocationReplay['changed']);
        self::assertSame($allocated['entitlement']->id, $allocationReplay['entitlement']->id);
        self::assertSame(2, $allocated['confirmed_units_after']);
        self::assertSame('free', $allocated['entitlement']->ticket_kind_snapshot->value);
        self::assertSame('0.00', $allocated['entitlement']->unit_price_credits_snapshot);
        self::assertSame('0.00', $allocated['entitlement']->total_price_credits_snapshot);
        self::assertSame(1, DB::table('event_ticket_entitlements')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', (int) $ticket->id)
            ->count());
        self::assertSame(['confirmed'], DB::table('event_ticket_entitlement_history')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', (int) $ticket->id)
            ->orderBy('id')->pluck('action')->all());
        self::assertSame(['allocated'], DB::table('event_ticket_inventory_history')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', (int) $ticket->id)
            ->orderBy('id')->pluck('action')->all());
        $this->assertReason(
            'event_ticket_entitlement_idempotency_conflict',
            fn () => $service->allocateSelf(
                $eventId,
                (int) $ticket->id,
                $registrationId,
                $member,
                1,
                'free-entitlement-allocate',
            ),
        );

        $cancelled = $service->cancel(
            $eventId,
            (int) $allocated['entitlement']->id,
            $member,
            1,
            'Member cancelled the ticket.',
            'free-entitlement-cancel',
        );
        $cancellationReplay = $service->cancel(
            $eventId,
            (int) $allocated['entitlement']->id,
            $member,
            1,
            'Member cancelled the ticket.',
            'free-entitlement-cancel',
        );

        self::assertTrue($cancelled['changed']);
        self::assertFalse($cancellationReplay['changed']);
        self::assertSame(0, $cancelled['confirmed_units_after']);
        self::assertSame('cancelled', $cancelled['entitlement']->status->value);
        self::assertSame(2, (int) $cancelled['entitlement']->entitlement_version);
        self::assertSame(
            ['confirmed', 'cancelled'],
            DB::table('event_ticket_entitlement_history')
                ->where('tenant_id', $this->testTenantId)
                ->where('event_id', $eventId)
                ->where('ticket_type_id', (int) $ticket->id)
                ->orderBy('id')->pluck('action')->all(),
        );
        self::assertSame(
            ['allocated', 'released'],
            DB::table('event_ticket_inventory_history')
                ->where('tenant_id', $this->testTenantId)
                ->where('event_id', $eventId)
                ->where('ticket_type_id', (int) $ticket->id)
                ->orderBy('id')->pluck('action')->all(),
        );
        self::assertSame([2, -2], DB::table('event_ticket_inventory_history')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', (int) $ticket->id)
            ->orderBy('id')->pluck('quantity_delta')->map(static fn (mixed $value): int => (int) $value)->all());
        self::assertSame(
            $unrelatedBefore,
            $this->unrelatedSnapshot(
                $this->testTenantId,
                $eventId,
                [(int) $owner->id, (int) $member->id],
            ),
        );
    }

    public function test_allocation_requires_policy_visibility_canonical_confirmation_eligibility_and_capacity(): void
    {
        $owner = $this->ticketUser();
        $member = $this->ticketUser();
        $otherMember = $this->ticketUser();
        $unapproved = $this->ticketUser(['is_approved' => false]);
        $nonManager = $this->ticketUser();
        $crossTenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Ticket cross-tenant fixture',
            'slug' => 'ticket-cross-' . bin2hex(random_bytes(8)),
            'features' => json_encode(['events' => true], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $crossTenantMember = $this->ticketUser([], $crossTenantId);
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        $memberRegistration = $this->ticketRegistration($eventId, (int) $member->id);
        $otherRegistration = $this->ticketRegistration($eventId, (int) $otherMember->id);
        $unapprovedRegistration = $this->ticketRegistration($eventId, (int) $unapproved->id);
        $pendingRegistration = $this->ticketRegistration($eventId, (int) $nonManager->id, 'pending');
        $limited = $this->activeTicketType($eventId, $owner, $start, [
            'name' => 'One-place ticket',
            'allocation_limit' => 1,
            'per_member_limit' => 1,
        ]);
        $perMember = $this->activeTicketType($eventId, $owner, $start, [
            'name' => 'Per-member ticket',
            'allocation_limit' => 5,
            'per_member_limit' => 1,
        ]);
        $futureWindow = $this->activeTicketType($eventId, $owner, $start, [
            'name' => 'Future-window ticket',
            'sales_opens_at' => CarbonImmutable::now('UTC')->addDay()->format('Y-m-d\TH:i:sP'),
        ]);
        $service = new EventTicketEntitlementService();

        $this->assertReason(
            'event_ticket_manage_finance_denied',
            fn () => $service->allocateForMember(
                $eventId,
                (int) $limited->id,
                $memberRegistration,
                (int) $member->id,
                $nonManager,
                1,
                'allocation-non-manager',
            ),
        );
        $this->assertReason(
            'event_ticket_actor_not_found',
            fn () => $service->allocateForMember(
                $eventId,
                (int) $limited->id,
                $memberRegistration,
                (int) $crossTenantMember->id,
                $owner,
                1,
                'allocation-cross-tenant',
            ),
        );
        $this->assertReason(
            'event_ticket_confirmed_registration_required',
            fn () => $service->allocateSelf(
                $eventId,
                (int) $limited->id,
                $pendingRegistration,
                $nonManager,
                1,
                'allocation-pending-registration',
            ),
        );
        $this->assertReason(
            'event_ticket_eligibility_denied',
            fn () => $service->allocateSelf(
                $eventId,
                (int) $limited->id,
                $unapprovedRegistration,
                $unapproved,
                1,
                'allocation-unapproved',
            ),
        );
        $this->assertReason(
            'event_ticket_sales_window_closed',
            fn () => $service->allocateSelf(
                $eventId,
                (int) $futureWindow->id,
                $memberRegistration,
                $member,
                1,
                'allocation-future-window',
            ),
        );

        $service->allocateSelf(
            $eventId,
            (int) $limited->id,
            $memberRegistration,
            $member,
            1,
            'allocation-limited-first',
        );
        $this->assertReason(
            'event_ticket_allocation_exhausted',
            fn () => $service->allocateSelf(
                $eventId,
                (int) $limited->id,
                $otherRegistration,
                $otherMember,
                1,
                'allocation-limited-second',
            ),
        );
        $service->allocateSelf(
            $eventId,
            (int) $perMember->id,
            $memberRegistration,
            $member,
            1,
            'allocation-per-member-first',
        );
        $this->assertReason(
            'event_ticket_per_member_limit_exceeded',
            fn () => $service->allocateSelf(
                $eventId,
                (int) $perMember->id,
                $memberRegistration,
                $member,
                1,
                'allocation-per-member-second',
            ),
        );
    }

    /** @return array<string,mixed> */
    private function unrelatedSnapshot(int $tenantId, int $eventId, array $userIds): array
    {
        $snapshot = [];
        foreach (self::UNRELATED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $snapshot[$table] = null;
                continue;
            }
            $query = DB::table($table);
            if (Schema::hasColumn($table, 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }
            if (Schema::hasColumn($table, 'event_id')) {
                $query->where('event_id', $eventId);
            }
            if (Schema::hasColumn($table, 'id')) {
                $query->orderBy('id');
            }
            $snapshot[$table] = $query->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();
        }
        $snapshot['user_balances'] = Schema::hasColumn('users', 'balance')
            ? DB::table('users')->whereIn('id', $userIds)->orderBy('id')->pluck('balance', 'id')->all()
            : null;

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
