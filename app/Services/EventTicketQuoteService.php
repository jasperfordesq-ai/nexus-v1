<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventTicketKind;
use App\Enums\EventTicketTypeStatus;
use App\Exceptions\EventTicketingException;
use App\Models\User;
use App\Support\Events\EventTicketEligibilityPolicy;
use App\Support\Events\EventTicketingSupport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only free/time-credit quote; never materialises credit effects. */
final class EventTicketQuoteService
{
    public function __construct(
        private readonly EventTicketingSupport $support = new EventTicketingSupport(),
        private readonly EventTicketEligibilityPolicy $eligibility = new EventTicketEligibilityPolicy(),
    ) {
    }

    /**
     * @return array{
     *   ticket_type_id:int,
     *   kind:string,
     *   units:int,
     *   unit_price_credits:string,
     *   total_price_credits:string,
     *   status:string,
     *   eligibility:array{eligible:bool,reasons:list<string>},
     *   allocation_remaining:int,
     *   member_remaining:int,
     *   sales_window_open:bool,
     *   materialization_supported:bool,
     *   gateway_status:string,
     *   attendance_reward_included:false,
     *   refund_policy:array{cutoff_at_utc:?string,organizer_cancel_refundable:bool,execution_status:string}
     * }
     */
    public function quote(
        int $eventId,
        int $ticketTypeId,
        User|int $member,
        int $units = 1,
    ): array {
        if (! Schema::hasTable('event_ticket_types')
            || ! Schema::hasTable('event_ticket_entitlements')) {
            throw new EventTicketingException('event_ticket_type_schema_unavailable');
        }
        if ($units < 1 || $units > 1000) {
            throw new EventTicketingException('event_ticket_units_invalid');
        }
        $tenantId = $this->support->tenantId();
        $event = $this->support->concreteEvent($tenantId, $eventId, false);
        $persistedMember = $this->support->actor($tenantId, $member, false);
        $this->support->authorizeView($persistedMember, $event);
        $ticket = DB::table('event_ticket_types')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', $ticketTypeId)
            ->first();
        if ($ticket === null) {
            throw new EventTicketingException('event_ticket_type_not_found');
        }
        $kind = EventTicketKind::from((string) $ticket->kind);
        $unitCents = $this->support->creditCents((string) $ticket->unit_price_credits);
        $totalCents = $unitCents * $units;
        $policy = json_decode((string) $ticket->eligibility_policy, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($policy)) {
            throw new EventTicketingException('event_ticket_eligibility_policy_invalid');
        }
        /** @var array{approved_member_required:bool,minimum_account_age_days:int,required_group_ids:list<int>} $policy */
        $eligibility = $this->eligibility->evaluate($tenantId, $persistedMember, $policy);
        $confirmed = $this->support->confirmedUnits($tenantId, $eventId, $ticketTypeId);
        $memberUnits = (int) DB::table('event_ticket_entitlements')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', $ticketTypeId)
            ->where('user_id', (int) $persistedMember->id)
            ->where('status', 'confirmed')
            ->sum('units');
        $now = CarbonImmutable::now('UTC');
        $opens = CarbonImmutable::parse((string) $ticket->sales_opens_at_utc, 'UTC')->utc();
        $closes = CarbonImmutable::parse((string) $ticket->sales_closes_at_utc, 'UTC')->utc();
        $windowOpen = (string) $ticket->status === EventTicketTypeStatus::Active->value
            && ! $now->lessThan($opens)
            && $now->lessThan($closes);

        return [
            'ticket_type_id' => (int) $ticket->id,
            'kind' => $kind->value,
            'units' => $units,
            'unit_price_credits' => $this->support->credits($unitCents),
            'total_price_credits' => $this->support->credits($totalCents),
            'status' => (string) $ticket->status,
            'eligibility' => $eligibility,
            'allocation_remaining' => max(0, (int) $ticket->allocation_limit - $confirmed),
            'member_remaining' => max(0, (int) $ticket->per_member_limit - $memberUnits),
            'sales_window_open' => $windowOpen,
            'materialization_supported' => $kind === EventTicketKind::Free,
            'gateway_status' => $kind === EventTicketKind::TimeCredit
                ? 'unavailable'
                : 'not_required',
            'attendance_reward_included' => false,
            'refund_policy' => [
                'cutoff_at_utc' => $ticket->refund_cutoff_at_utc === null
                    ? null
                    : CarbonImmutable::parse((string) $ticket->refund_cutoff_at_utc, 'UTC')
                        ->utc()->toIso8601String(),
                'organizer_cancel_refundable' => (bool) $ticket->organizer_cancel_refundable,
                'execution_status' => 'not_integrated',
            ],
        ];
    }
}
