<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EventTicketEntitlement;
use App\Models\EventTicketType;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Throwable;

/** Explicit, identity-free resources for Event ticket configuration and ownership. */
final class EventTicketResource
{
    /** @param array<string,mixed> $quote @return array<string,mixed> */
    public static function quote(array $quote): array
    {
        $eligibility = self::array($quote['eligibility'] ?? null);
        $refund = self::array($quote['refund_policy'] ?? null);

        return [
            'ticket_type_id' => self::positiveInt($quote['ticket_type_id'] ?? null),
            'kind' => self::enum($quote['kind'] ?? 'free'),
            'units' => self::count($quote['units'] ?? 0),
            'unit_price_credits' => self::decimal($quote['unit_price_credits'] ?? 0),
            'total_price_credits' => self::decimal($quote['total_price_credits'] ?? 0),
            'status' => self::enum($quote['status'] ?? 'draft'),
            'eligibility' => [
                'eligible' => (bool) ($eligibility['eligible'] ?? false),
                'reasons' => array_values(array_filter(
                    self::list($eligibility['reasons'] ?? null),
                    static fn (mixed $reason): bool => is_string($reason) && $reason !== '',
                )),
            ],
            'allocation_remaining' => self::count($quote['allocation_remaining'] ?? 0),
            'member_remaining' => self::count($quote['member_remaining'] ?? 0),
            'sales_window_open' => (bool) ($quote['sales_window_open'] ?? false),
            'materialization_supported' => (bool) ($quote['materialization_supported'] ?? false),
            'gateway_status' => self::text($quote['gateway_status'] ?? null) ?? 'unavailable',
            'attendance_reward_included' => false,
            'refund_policy' => [
                'cutoff_at' => self::date($refund['cutoff_at_utc'] ?? null),
                'organizer_cancel_refundable' => (bool) ($refund['organizer_cancel_refundable'] ?? false),
                'execution_status' => self::text($refund['execution_status'] ?? null) ?? 'not_integrated',
            ],
        ];
    }

    /** @param array<string,mixed> $report @return array<string,mixed> */
    public static function reconciliation(array $report): array
    {
        $rows = [];
        foreach (self::list($report['ticket_types'] ?? null) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows[] = [
                'ticket_type_id' => self::positiveInt($row['ticket_type_id'] ?? null),
                'kind' => self::enum($row['kind'] ?? 'free'),
                'status' => self::enum($row['status'] ?? 'draft'),
                'allocation_limit' => self::count($row['allocation_limit'] ?? 0),
                'confirmed_units' => self::count($row['confirmed_units'] ?? 0),
                'cancelled_units' => self::count($row['cancelled_units'] ?? 0),
                'confirmed_entitlements' => self::count($row['confirmed_entitlements'] ?? 0),
                'cancelled_entitlements' => self::count($row['cancelled_entitlements'] ?? 0),
                'registration_mismatches' => self::count($row['registration_mismatches'] ?? 0),
                'price_snapshot_violations' => self::count($row['price_snapshot_violations'] ?? 0),
                'inventory_delta' => self::int($row['inventory_delta'] ?? 0),
                'latest_inventory_after' => self::count($row['latest_inventory_after'] ?? 0),
                'allocation_overrun' => (bool) ($row['allocation_overrun'] ?? false),
                'inventory_mismatch' => (bool) ($row['inventory_mismatch'] ?? false),
            ];
        }

        return [
            'event_id' => self::positiveInt($report['event_id'] ?? null),
            'read_only' => true,
            'ticket_types' => $rows,
        ];
    }

    /** @param array<string,mixed> $catalogue @return array<string,mixed> */
    public static function catalogue(array $catalogue): array
    {
        $types = [];
        foreach (self::list($catalogue['ticket_types'] ?? null) as $type) {
            if (is_array($type)) {
                $types[] = self::type($type);
            }
        }
        $entitlements = [];
        foreach (self::list($catalogue['own_entitlements'] ?? null) as $entitlement) {
            if (is_array($entitlement)) {
                $entitlements[] = self::entitlement($entitlement);
            }
        }
        $gateway = self::array($catalogue['payment_gateway'] ?? null);
        $permissions = self::array($catalogue['permissions'] ?? null);

        return [
            'contract_version' => 1,
            'event_id' => self::positiveInt($catalogue['event_id'] ?? null),
            'currency' => 'time_credit',
            'payment_gateway' => [
                'free_supported' => (bool) ($gateway['free_supported'] ?? true),
                'time_credit_supported' => (bool) ($gateway['time_credit_supported'] ?? false),
                'money_supported' => false,
            ],
            'permissions' => [
                'manage' => (bool) ($permissions['manage'] ?? false),
                'reconcile' => (bool) ($permissions['reconcile'] ?? false),
                'allocate_self' => (bool) ($permissions['allocate_self'] ?? false),
            ],
            'ticket_types' => $types,
            'own_entitlements' => $entitlements,
        ];
    }

    /** @param EventTicketType|array<string,mixed> $source @return array<string,mixed> */
    public static function type(EventTicketType|array $source): array
    {
        $type = $source instanceof EventTicketType ? $source->toArray() : $source;
        $availability = self::array($type['availability'] ?? null);
        $eligibility = self::array($availability['eligibility'] ?? null);
        $refund = self::array($availability['refund_policy'] ?? null);
        $policy = $type['eligibility_policy'] ?? null;

        return [
            'id' => self::positiveInt($type['id'] ?? null),
            'version' => max(1, self::int($type['version'] ?? $type['ticket_version'] ?? 1)),
            'name' => self::text($type['name'] ?? null) ?? '',
            'description' => self::text($type['description'] ?? null),
            'kind' => self::enum($type['kind'] ?? 'free'),
            'unit_price_credits' => self::decimal($type['unit_price_credits'] ?? 0),
            'allocation_limit' => self::count($type['allocation_limit'] ?? 0),
            'sales_opens_at' => self::date($type['sales_opens_at'] ?? $type['sales_opens_at_utc'] ?? null),
            'sales_closes_at' => self::date($type['sales_closes_at'] ?? $type['sales_closes_at_utc'] ?? null),
            'per_member_limit' => self::count($type['per_member_limit'] ?? 0),
            'refund_cutoff_at' => self::date($type['refund_cutoff_at'] ?? $type['refund_cutoff_at_utc'] ?? null),
            'organizer_cancel_refundable' => (bool) ($type['organizer_cancel_refundable'] ?? false),
            'status' => self::enum($type['status'] ?? 'draft'),
            'availability' => [
                'eligibility' => [
                    'eligible' => (bool) ($eligibility['eligible'] ?? false),
                    'reasons' => array_values(array_filter(
                        self::list($eligibility['reasons'] ?? null),
                        static fn (mixed $reason): bool => is_string($reason) && $reason !== '',
                    )),
                ],
                'allocation_remaining' => self::count($availability['allocation_remaining'] ?? 0),
                'member_remaining' => self::count($availability['member_remaining'] ?? 0),
                'sales_window_open' => (bool) ($availability['sales_window_open'] ?? false),
                'materialization_supported' => (bool) ($availability['materialization_supported'] ?? false),
                'gateway_status' => self::text($availability['gateway_status'] ?? null) ?? 'unavailable',
                'attendance_reward_included' => false,
                'refund_policy' => [
                    'cutoff_at' => self::date($refund['cutoff_at_utc'] ?? null),
                    'organizer_cancel_refundable' => (bool) ($refund['organizer_cancel_refundable'] ?? false),
                    'execution_status' => self::text($refund['execution_status'] ?? null) ?? 'not_integrated',
                ],
            ],
            'eligibility_policy' => is_array($policy) ? [
                'approved_member_required' => (bool) ($policy['approved_member_required'] ?? false),
                'minimum_account_age_days' => self::count($policy['minimum_account_age_days'] ?? 0),
                'required_group_ids' => array_values(array_filter(array_map(
                    static fn (mixed $id): ?int => is_numeric($id) && (int) $id > 0 ? (int) $id : null,
                    self::list($policy['required_group_ids'] ?? null),
                ))),
            ] : null,
        ];
    }

    /** @param EventTicketEntitlement|array<string,mixed> $source @return array<string,mixed> */
    public static function entitlement(EventTicketEntitlement|array $source): array
    {
        $value = $source instanceof EventTicketEntitlement ? $source->toArray() : $source;

        return [
            'id' => self::positiveInt($value['id'] ?? null),
            'ticket_type_id' => self::positiveInt($value['ticket_type_id'] ?? null),
            'units' => self::count($value['units'] ?? 0),
            'kind' => self::enum($value['kind'] ?? $value['ticket_kind_snapshot'] ?? 'free'),
            'unit_price_credits' => self::decimal(
                $value['unit_price_credits'] ?? $value['unit_price_credits_snapshot'] ?? 0,
            ),
            'total_price_credits' => self::decimal(
                $value['total_price_credits'] ?? $value['total_price_credits_snapshot'] ?? 0,
            ),
            'status' => self::enum($value['status'] ?? 'confirmed'),
            'version' => max(1, self::int(
                $value['version'] ?? $value['entitlement_version'] ?? 1,
            )),
            'confirmed_at' => self::date($value['confirmed_at'] ?? null),
            'cancelled_at' => self::date($value['cancelled_at'] ?? null),
        ];
    }

    /** @return array<string,mixed> */
    private static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return list<mixed> */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private static function positiveInt(mixed $value): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : 0;
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function count(mixed $value): int
    {
        return max(0, self::int($value));
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim(strip_tags($value));

        return $value === '' ? null : $value;
    }

    private static function enum(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : trim((string) $value);
    }

    private static function decimal(mixed $value): string
    {
        return number_format(is_numeric($value) ? (float) $value : 0, 2, '.', '');
    }

    private static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value instanceof DateTimeInterface ? $value : (string) $value)
                ->utc()
                ->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
