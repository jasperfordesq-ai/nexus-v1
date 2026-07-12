<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EventTicketingException;
use App\Models\User;
use App\Support\Events\EventTicketingSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only ticket inventory/registration evidence comparison. */
final class EventTicketReconciliationService
{
    public function __construct(
        private readonly EventTicketingSupport $support = new EventTicketingSupport(),
    ) {
    }

    /**
     * @return array{
     *   event_id:int,
     *   read_only:true,
     *   ticket_types:list<array{
     *     ticket_type_id:int,
     *     kind:string,
     *     status:string,
     *     allocation_limit:int,
     *     confirmed_units:int,
     *     cancelled_units:int,
     *     confirmed_entitlements:int,
     *     cancelled_entitlements:int,
     *     registration_mismatches:int,
     *     price_snapshot_violations:int,
     *     inventory_delta:int,
     *     latest_inventory_after:int,
     *     allocation_overrun:bool,
     *     inventory_mismatch:bool
     *   }>
     * }
     */
    public function report(int $eventId, User|int $actor): array
    {
        foreach ([
            'event_ticket_types',
            'event_ticket_entitlements',
            'event_ticket_inventory_history',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new EventTicketingException('event_ticket_reconciliation_schema_unavailable');
            }
        }
        $tenantId = $this->support->tenantId();
        $event = $this->support->concreteEvent($tenantId, $eventId, false);
        $persistedActor = $this->support->actor($tenantId, $actor, false);
        $this->support->authorizeReconcileTickets($persistedActor, $event);

        $types = DB::table('event_ticket_types')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->orderBy('id')
            ->get(['id', 'kind', 'status', 'allocation_limit']);
        $entitlements = DB::table('event_ticket_entitlements as entitlement')
            ->leftJoin('event_registrations as registration', function ($join): void {
                $join->on('registration.tenant_id', '=', 'entitlement.tenant_id')
                    ->on('registration.event_id', '=', 'entitlement.event_id')
                    ->on('registration.id', '=', 'entitlement.registration_id')
                    ->on('registration.user_id', '=', 'entitlement.user_id');
            })
            ->where('entitlement.tenant_id', $tenantId)
            ->where('entitlement.event_id', $eventId)
            ->groupBy('entitlement.ticket_type_id')
            ->get([
                'entitlement.ticket_type_id',
                DB::raw("SUM(CASE WHEN entitlement.status = 'confirmed' THEN entitlement.units ELSE 0 END) AS confirmed_units"),
                DB::raw("SUM(CASE WHEN entitlement.status = 'cancelled' THEN entitlement.units ELSE 0 END) AS cancelled_units"),
                DB::raw("SUM(CASE WHEN entitlement.status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_entitlements"),
                DB::raw("SUM(CASE WHEN entitlement.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_entitlements"),
                DB::raw("SUM(CASE WHEN entitlement.status = 'confirmed' AND (registration.id IS NULL OR registration.registration_state <> 'confirmed') THEN 1 ELSE 0 END) AS registration_mismatches"),
                DB::raw("SUM(CASE WHEN entitlement.ticket_kind_snapshot <> 'free' OR entitlement.unit_price_credits_snapshot <> 0.00 OR entitlement.total_price_credits_snapshot <> 0.00 THEN 1 ELSE 0 END) AS price_snapshot_violations"),
            ])
            ->keyBy('ticket_type_id');
        $inventory = DB::table('event_ticket_inventory_history')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->groupBy('ticket_type_id')
            ->get([
                'ticket_type_id',
                DB::raw('SUM(quantity_delta) AS inventory_delta'),
            ])
            ->keyBy('ticket_type_id');
        $latestInventoryIds = DB::table('event_ticket_inventory_history')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->groupBy('ticket_type_id')
            ->select(['ticket_type_id', DB::raw('MAX(id) AS latest_id')]);
        $latestInventory = DB::table('event_ticket_inventory_history as history')
            ->joinSub($latestInventoryIds, 'latest', function ($join): void {
                $join->on('history.ticket_type_id', '=', 'latest.ticket_type_id')
                    ->on('history.id', '=', 'latest.latest_id');
            })
            ->where('history.tenant_id', $tenantId)
            ->where('history.event_id', $eventId)
            ->get(['history.ticket_type_id', 'history.confirmed_units_after'])
            ->keyBy('ticket_type_id');

        $rows = [];
        foreach ($types as $type) {
            $entitlement = $entitlements->get($type->id);
            $inventoryRow = $inventory->get($type->id);
            $latestInventoryRow = $latestInventory->get($type->id);
            $confirmed = (int) ($entitlement->confirmed_units ?? 0);
            $delta = (int) ($inventoryRow->inventory_delta ?? 0);
            $latest = (int) ($latestInventoryRow->confirmed_units_after ?? 0);
            $rows[] = [
                'ticket_type_id' => (int) $type->id,
                'kind' => (string) $type->kind,
                'status' => (string) $type->status,
                'allocation_limit' => (int) $type->allocation_limit,
                'confirmed_units' => $confirmed,
                'cancelled_units' => (int) ($entitlement->cancelled_units ?? 0),
                'confirmed_entitlements' => (int) ($entitlement->confirmed_entitlements ?? 0),
                'cancelled_entitlements' => (int) ($entitlement->cancelled_entitlements ?? 0),
                'registration_mismatches' => (int) ($entitlement->registration_mismatches ?? 0),
                'price_snapshot_violations' => (int) ($entitlement->price_snapshot_violations ?? 0),
                'inventory_delta' => $delta,
                'latest_inventory_after' => $latest,
                'allocation_overrun' => $confirmed > (int) $type->allocation_limit,
                'inventory_mismatch' => $delta !== $confirmed || $latest !== $confirmed,
            ];
        }

        return ['event_id' => $eventId, 'read_only' => true, 'ticket_types' => $rows];
    }
}
