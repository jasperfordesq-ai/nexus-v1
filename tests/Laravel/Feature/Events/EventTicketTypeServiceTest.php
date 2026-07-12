<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Exceptions\EventTicketingException;
use App\Services\EventTicketTypeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventTicketingFixtures;
use Tests\Laravel\TestCase;

final class EventTicketTypeServiceTest extends TestCase
{
    use DatabaseTransactions;
    use BuildsEventTicketingFixtures;

    public function test_type_validation_forbids_money_and_strictly_validates_credit_and_eligibility_rules(): void
    {
        $owner = $this->ticketUser();
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        $service = new EventTicketTypeService();
        $this->assertReason(
            'event_ticket_type_fields_unknown',
            fn () => $service->create(
                $eventId,
                $owner,
                $this->ticketTypePayload($start, ['currency' => 'EUR']),
                'ticket-money-field',
            ),
        );
        $this->assertReason(
            'event_ticket_kind_invalid',
            fn () => $service->create(
                $eventId,
                $owner,
                $this->ticketTypePayload($start, ['kind' => 'monetary']),
                'ticket-money-kind',
            ),
        );
        $this->assertReason(
            'event_ticket_kind_price_mismatch',
            fn () => $service->create(
                $eventId,
                $owner,
                $this->ticketTypePayload($start, ['unit_price_credits' => '1.00']),
                'ticket-free-positive-price',
            ),
        );
        $this->assertReason(
            'event_ticket_kind_price_mismatch',
            fn () => $service->create(
                $eventId,
                $owner,
                $this->ticketTypePayload($start, [
                    'kind' => 'time_credit',
                    'unit_price_credits' => '0.00',
                ]),
                'ticket-credit-zero-price',
            ),
        );
        $this->assertReason(
            'event_ticket_eligibility_policy_fields_unknown',
            fn () => $service->create(
                $eventId,
                $owner,
                $this->ticketTypePayload($start, [
                    'eligibility_policy' => ['sql' => 'user supplied predicate'],
                ]),
                'ticket-eligibility-unknown',
            ),
        );
        $this->assertReason(
            'event_ticket_eligibility_approval_invalid',
            fn () => $service->create(
                $eventId,
                $owner,
                $this->ticketTypePayload($start, [
                    'eligibility_policy' => ['approved_member_required' => 'yes'],
                ]),
                'ticket-eligibility-non-boolean',
            ),
        );
        $this->assertReason(
            'event_ticket_eligibility_account_age_invalid',
            fn () => $service->create(
                $eventId,
                $owner,
                $this->ticketTypePayload($start, [
                    'eligibility_policy' => ['minimum_account_age_days' => '30'],
                ]),
                'ticket-eligibility-non-integer',
            ),
        );
        $this->assertReason(
            'event_ticket_refund_policy_invalid',
            fn () => $service->create(
                $eventId,
                $owner,
                $this->ticketTypePayload($start, ['organizer_cancel_refundable' => 'true']),
                'ticket-refund-non-boolean',
            ),
        );
        $credit = $service->create(
            $eventId,
            $owner,
            $this->ticketTypePayload($start, [
                'name' => 'Time-credit ticket',
                'kind' => 'time_credit',
                'unit_price_credits' => '2.50',
            ]),
            'ticket-credit-valid',
        );
        self::assertSame('time_credit', $credit['ticket_type']->kind->value);
        self::assertSame('2.50', $credit['ticket_type']->unit_price_credits);
    }

    public function test_timezone_version_idempotency_lifecycle_and_archive_are_strict(): void
    {
        $owner = $this->ticketUser();
        $start = CarbonImmutable::create(2027, 7, 20, 12, 0, 0, 'Europe/Dublin');
        [$eventId] = $this->ticketEvent(
            (int) $owner->id,
            $start,
            $start->addHours(3),
            'Europe/Dublin',
        );
        $service = new EventTicketTypeService();
        $payload = $this->ticketTypePayload($start);
        $created = $service->create($eventId, $owner, $payload, 'ticket-type-idempotent-create');
        $replay = $service->create($eventId, $owner, $payload, 'ticket-type-idempotent-create');
        self::assertTrue($created['changed']);
        self::assertFalse($replay['changed']);
        self::assertSame($created['ticket_type']->id, $replay['ticket_type']->id);
        self::assertSame(1, DB::table('event_ticket_types')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->count());
        self::assertSame(1, DB::table('event_ticket_type_history')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', (int) $created['ticket_type']->id)
            ->count());
        $this->assertReason(
            'event_ticket_type_idempotency_conflict',
            fn () => $service->create(
                $eventId,
                $owner,
                [...$payload, 'name' => 'Changed replay'],
                'ticket-type-idempotent-create',
            ),
        );
        $this->assertReason(
            'event_ticket_timezone_offset_mismatch',
            fn () => $service->update(
                $eventId,
                (int) $created['ticket_type']->id,
                $owner,
                ['sales_opens_at' => '2027-07-01T12:00:00+00:00'],
                1,
                'ticket-type-bad-offset',
            ),
        );
        $this->assertReason(
            'event_ticket_sales_open_invalid',
            fn () => $service->update(
                $eventId,
                (int) $created['ticket_type']->id,
                $owner,
                ['sales_opens_at' => '2027-07-01 12:00:00'],
                1,
                'ticket-type-missing-offset',
            ),
        );
        $updated = $service->update(
            $eventId,
            (int) $created['ticket_type']->id,
            $owner,
            ['name' => 'Renamed ticket'],
            1,
            'ticket-type-update',
        );
        self::assertSame(2, (int) $updated['ticket_type']->ticket_version);
        $active = $service->activate(
            $eventId,
            (int) $updated['ticket_type']->id,
            $owner,
            2,
            'ticket-type-activate',
        );
        self::assertSame('active', $active['ticket_type']->status->value);
        $this->assertReason(
            'event_ticket_type_not_editable',
            fn () => $service->update(
                $eventId,
                (int) $active['ticket_type']->id,
                $owner,
                ['name' => 'Unsafe active edit'],
                3,
                'ticket-type-active-edit',
            ),
        );
        $paused = $service->pause(
            $eventId,
            (int) $active['ticket_type']->id,
            $owner,
            3,
            'ticket-type-pause',
            'Organizer review',
        );
        $pausedUpdate = $service->update(
            $eventId,
            (int) $paused['ticket_type']->id,
            $owner,
            ['description' => 'Updated while paused.'],
            4,
            'ticket-type-paused-update',
        );
        $reactivated = $service->activate(
            $eventId,
            (int) $pausedUpdate['ticket_type']->id,
            $owner,
            5,
            'ticket-type-reactivate',
        );
        $archived = $service->archive(
            $eventId,
            (int) $reactivated['ticket_type']->id,
            $owner,
            6,
            'ticket-type-archive',
            'Ticket class retired',
        );
        $archiveReplay = $service->archive(
            $eventId,
            (int) $reactivated['ticket_type']->id,
            $owner,
            6,
            'ticket-type-archive',
            'Ticket class retired',
        );
        self::assertSame('archived', $archived['ticket_type']->status->value);
        self::assertFalse($archiveReplay['changed']);
        self::assertSame(
            ['created', 'updated', 'activated', 'paused', 'updated', 'activated', 'archived'],
            DB::table('event_ticket_type_history')
                ->where('tenant_id', $this->testTenantId)
                ->where('event_id', $eventId)
                ->where('ticket_type_id', (int) $created['ticket_type']->id)
                ->orderBy('id')->pluck('action')->all(),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('event_ticket_type_delete_forbidden');
        $archived['ticket_type']->delete();
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
