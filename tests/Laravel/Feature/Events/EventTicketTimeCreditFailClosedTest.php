<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Exceptions\EventTicketingException;
use App\Services\EventTicketEntitlementService;
use App\Services\EventTicketQuoteService;
use App\Services\EventTicketTypeService;
use App\Services\EventTimeCreditTicketGatewayService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventTicketingFixtures;
use Tests\Laravel\TestCase;

final class EventTicketTimeCreditFailClosedTest extends TestCase
{
    use DatabaseTransactions;
    use BuildsEventTicketingFixtures;

    public function test_time_credit_quotes_are_read_only_and_every_effect_path_fails_closed_without_mutation(): void
    {
        $owner = $this->ticketUser(['balance' => '25.00']);
        $member = $this->ticketUser(['balance' => '10.00']);
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        $registrationId = $this->ticketRegistration($eventId, (int) $member->id);
        $types = new EventTicketTypeService();
        $created = $types->create(
            $eventId,
            $owner,
            $this->ticketTypePayload($start, [
                'name' => 'Time-credit ticket',
                'kind' => 'time_credit',
                'unit_price_credits' => '2.50',
            ]),
            'time-credit-ticket-draft',
        );
        $ticket = $created['ticket_type'];
        $before = $this->stateSnapshot(
            $this->testTenantId,
            $eventId,
            [(int) $owner->id, (int) $member->id],
        );

        $quote = (new EventTicketQuoteService())->quote(
            $eventId,
            (int) $ticket->id,
            $member,
            2,
        );
        self::assertSame('time_credit', $quote['kind']);
        self::assertSame('2.50', $quote['unit_price_credits']);
        self::assertSame('5.00', $quote['total_price_credits']);
        self::assertTrue($quote['eligibility']['eligible']);
        self::assertFalse($quote['materialization_supported']);
        self::assertSame('unavailable', $quote['gateway_status']);
        self::assertFalse($quote['attendance_reward_included']);
        self::assertSame('not_integrated', $quote['refund_policy']['execution_status']);

        $this->assertGatewayUnavailable(fn () => $types->activate(
            $eventId,
            (int) $ticket->id,
            $owner,
            1,
            'time-credit-ticket-activation',
        ));

        $this->assertGatewayUnavailable(fn () => (new EventTicketEntitlementService())->allocateSelf(
            $eventId,
            (int) $ticket->id,
            $registrationId,
            $member,
            1,
            'time-credit-allocation',
        ));

        $gateway = new EventTimeCreditTicketGatewayService();
        foreach (['materialize', 'hold', 'settle', 'release', 'refund'] as $method) {
            $this->assertGatewayUnavailable(fn () => $gateway->{$method}(
                $eventId,
                (int) $ticket->id,
                (int) $member->id,
                '2.50',
            ));
        }

        self::assertSame(
            $before,
            $this->stateSnapshot(
                $this->testTenantId,
                $eventId,
                [(int) $owner->id, (int) $member->id],
            ),
        );
    }

    /** @return array<string,mixed> */
    private function stateSnapshot(int $tenantId, int $eventId, array $userIds): array
    {
        $tables = [
            'event_ticket_types',
            'event_ticket_type_history',
            'event_ticket_entitlements',
            'event_ticket_entitlement_history',
            'event_ticket_inventory_history',
            'event_registrations',
            'event_waitlist_entries',
            'event_attendance_activity',
            'event_invitations',
            'event_registration_form_answers',
            'transactions',
            'notifications',
            'event_federation_deliveries',
        ];
        $snapshot = [];
        foreach ($tables as $table) {
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
    private function assertGatewayUnavailable(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected event_ticket_time_credit_gateway_unavailable.');
        } catch (EventTicketingException $exception) {
            self::assertSame(
                'event_ticket_time_credit_gateway_unavailable',
                $exception->getMessage(),
            );
        }
    }
}
