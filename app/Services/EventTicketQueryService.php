<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventTicketTypeStatus;
use App\Exceptions\EventTicketingException;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Support\Events\EventTicketingSupport;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Policy-filtered ticket catalogue, own entitlements, and manager facts. */
final class EventTicketQueryService
{
    public function __construct(
        private readonly EventTicketingSupport $support = new EventTicketingSupport(),
        private readonly EventTicketQuoteService $quotes = new EventTicketQuoteService(),
        private readonly EventPolicy $policy = new EventPolicy(),
    ) {}

    /** @return array<string,mixed> */
    public function read(int $eventId, User|int $actor): array
    {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $event = $this->support->concreteEvent($tenantId, $eventId, false);
        $member = $this->support->actor($tenantId, $actor, false);
        $this->support->authorizeView($member, $event);
        $canManage = $this->policy->manageFinance($member, $event);
        $canReconcile = $this->policy->reconcileTickets($member, $event);

        $types = DB::table('event_ticket_types')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->when(! $canManage, static function ($query): void {
                $query->where('status', EventTicketTypeStatus::Active->value);
            })
            ->orderByRaw("FIELD(status, 'active', 'paused', 'draft', 'archived')")
            ->orderBy('sales_opens_at_utc')
            ->orderBy('id')
            ->limit(250)
            ->get();

        $catalogue = [];
        foreach ($types as $type) {
            $quote = $this->quotes->quote($eventId, (int) $type->id, $member, 1);
            $catalogue[] = [
                'id' => (int) $type->id,
                'version' => (int) $type->ticket_version,
                'name' => (string) $type->name,
                'description' => $type->description === null ? null : (string) $type->description,
                'kind' => (string) $type->kind,
                'unit_price_credits' => number_format((float) $type->unit_price_credits, 2, '.', ''),
                'allocation_limit' => (int) $type->allocation_limit,
                'sales_opens_at' => $this->iso($type->sales_opens_at_utc),
                'sales_closes_at' => $this->iso($type->sales_closes_at_utc),
                'per_member_limit' => (int) $type->per_member_limit,
                'refund_cutoff_at' => $this->iso($type->refund_cutoff_at_utc),
                'organizer_cancel_refundable' => (bool) $type->organizer_cancel_refundable,
                'status' => (string) $type->status,
                'availability' => $quote,
                'eligibility_policy' => $canManage
                    ? $this->policyArray($type->eligibility_policy)
                    : null,
            ];
        }

        $entitlements = DB::table('event_ticket_entitlements')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', (int) $member->id)
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->limit(250)
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'ticket_type_id' => (int) $row->ticket_type_id,
                'units' => (int) $row->units,
                'kind' => (string) $row->ticket_kind_snapshot,
                'unit_price_credits' => number_format(
                    (float) $row->unit_price_credits_snapshot,
                    2,
                    '.',
                    '',
                ),
                'total_price_credits' => number_format(
                    (float) $row->total_price_credits_snapshot,
                    2,
                    '.',
                    '',
                ),
                'status' => (string) $row->status,
                'version' => (int) $row->entitlement_version,
                'confirmed_at' => $this->iso($row->confirmed_at),
                'cancelled_at' => $this->iso($row->cancelled_at),
            ])
            ->values()
            ->all();

        $registrationId = DB::table('event_registrations')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', (int) $member->id)
            ->where('capacity_pool_key', 'event')
            ->where('registration_state', 'confirmed')
            ->value('id');

        return [
            'contract_version' => 1,
            'event_id' => $eventId,
            'currency' => 'time_credit',
            'payment_gateway' => [
                'free_supported' => true,
                'time_credit_supported' => false,
                'money_supported' => false,
            ],
            'permissions' => [
                'manage' => $canManage,
                'reconcile' => $canReconcile,
                'allocate_self' => $registrationId !== null,
            ],
            'ticket_types' => $catalogue,
            'own_entitlements' => $entitlements,
        ];
    }

    public function confirmedRegistrationId(int $eventId, int $userId): int
    {
        $tenantId = $this->support->tenantId();
        $id = DB::table('event_registrations')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('capacity_pool_key', 'event')
            ->where('registration_state', 'confirmed')
            ->value('id');
        if (! is_numeric($id) || (int) $id <= 0) {
            throw new EventTicketingException('event_ticket_confirmed_registration_required');
        }

        return (int) $id;
    }

    /** @return array<string,mixed> */
    private function policyArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value)) {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse(
                $value instanceof DateTimeInterface ? $value : (string) $value,
            )->utc()->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function assertSchema(): void
    {
        foreach (['event_ticket_types', 'event_ticket_entitlements', 'event_registrations'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new EventTicketingException('event_ticket_query_schema_unavailable');
            }
        }
    }
}
