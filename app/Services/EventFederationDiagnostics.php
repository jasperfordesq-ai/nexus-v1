<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventFederationDeliveryStatus;
use App\Models\Event;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Payload-free delivery and retry diagnostics for operators and organizers. */
final class EventFederationDiagnostics
{
    /** @return array<string,mixed> */
    public function eventStatus(Event $event): array
    {
        $tenantId = (int) $event->getAttribute('tenant_id');
        $eventId = (int) $event->getKey();
        $counts = array_fill_keys(array_map(
            static fn (EventFederationDeliveryStatus $status): string => $status->value,
            EventFederationDeliveryStatus::cases(),
        ), 0);
        foreach (DB::table('event_federation_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->selectRaw('status, COUNT(*) AS aggregate_count')
            ->groupBy('status')
            ->get() as $row) {
            if (array_key_exists((string) $row->status, $counts)) {
                $counts[(string) $row->status] = (int) $row->aggregate_count;
            }
        }

        $latestIds = DB::table('event_federation_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->groupBy('external_partner_id')
            ->selectRaw('MAX(id) AS latest_id')
            ->pluck('latest_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $partners = $latestIds === [] ? collect() : DB::table('event_federation_deliveries as delivery')
            ->leftJoin('federation_external_partners as partner', static function ($join) use ($tenantId): void {
                $join->on('partner.id', '=', 'delivery.external_partner_id')
                    ->where('partner.tenant_id', '=', $tenantId);
            })
            ->whereIn('delivery.id', $latestIds)
            ->orderBy('delivery.external_partner_id')
            ->get([
                'delivery.external_partner_id',
                'delivery.action',
                'delivery.status',
                'delivery.attempts',
                'delivery.event_aggregate_version',
                'delivery.event_calendar_version',
                'delivery.available_at',
                'delivery.next_attempt_at',
                'delivery.last_attempt_at',
                'delivery.delivered_at',
                'delivery.dead_lettered_at',
                'delivery.last_error_code',
                'partner.name as partner_name',
                'partner.status as partner_status',
                'partner.allow_events',
            ]);

        $configuredPartners = DB::table('federation_external_partners')
            ->where('tenant_id', $tenantId)
            ->where('protocol_type', 'nexus')
            ->where('status', 'active')
            ->where('allow_events', 1)
            ->count();
        $latest = $partners->map(static fn (object $row): array => [
            'partner_id' => (int) $row->external_partner_id,
            'partner_name' => self::nullableText($row->partner_name),
            'partner_status' => self::nullableText($row->partner_status) ?? 'removed',
            'events_enabled' => (bool) ($row->allow_events ?? false),
            'action' => (string) $row->action,
            'delivery_status' => (string) $row->status,
            'attempts' => (int) $row->attempts,
            'max_attempts' => EventFederationDeliveryLedger::MAX_ATTEMPTS,
            'aggregate_version' => (int) $row->event_aggregate_version,
            'calendar_version' => (int) $row->event_calendar_version,
            'available_at' => self::nullableText($row->available_at),
            'next_attempt_at' => self::nullableText($row->next_attempt_at),
            'last_attempt_at' => self::nullableText($row->last_attempt_at),
            'delivered_at' => self::nullableText($row->delivered_at),
            'dead_lettered_at' => self::nullableText($row->dead_lettered_at),
            'error_code' => self::nullableText($row->last_error_code),
        ])->all();

        return [
            'contract_version' => 1,
            'event_id' => $eventId,
            'federation_version' => max(1, (int) ($event->getAttribute('federation_version') ?? 1)),
            'visibility' => (string) ($event->getAttribute('federated_visibility') ?? 'none'),
            'configured_partners' => $configuredPartners,
            'recipient_partners' => count($latest),
            'health' => $this->health($configuredPartners, $counts, $latest),
            'counts' => $counts,
            'partners' => $latest,
            'generated_at' => now()->utc()->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    public function snapshot(?int $tenantId = null): array
    {
        $base = DB::table('event_federation_deliveries')
            ->when($tenantId !== null, static fn (Builder $query) => $query->where('tenant_id', $tenantId));
        $statuses = [];
        foreach ((clone $base)->selectRaw('status, COUNT(*) AS aggregate_count')->groupBy('status')->get() as $row) {
            $statuses[(string) $row->status] = (int) $row->aggregate_count;
        }
        $actions = [];
        foreach ((clone $base)->selectRaw('action, COUNT(*) AS aggregate_count')->groupBy('action')->get() as $row) {
            $actions[(string) $row->action] = (int) $row->aggregate_count;
        }
        $outstanding = (clone $base)->whereIn('status', [
            EventFederationDeliveryStatus::Pending->value,
            EventFederationDeliveryStatus::Retry->value,
            EventFederationDeliveryStatus::Processing->value,
            EventFederationDeliveryStatus::DeadLetter->value,
        ]);

        return [
            'contract_version' => 1,
            'tenant_id' => $tenantId,
            'total' => (clone $base)->count(),
            'by_status' => $statuses,
            'by_action' => $actions,
            'due' => (clone $base)
                ->whereIn('status', ['pending', 'retry'])
                ->where(static function (Builder $query): void {
                    $query->whereNull('available_at')->orWhere('available_at', '<=', now());
                })
                ->where(static function (Builder $query): void {
                    $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                })
                ->count(),
            'stale_processing' => (clone $base)
                ->where('status', EventFederationDeliveryStatus::Processing->value)
                ->where('claimed_at', '<', now()->subMinutes(EventFederationDeliveryLedger::STALE_CLAIM_MINUTES))
                ->count(),
            'dead_lettered' => (int) ($statuses[EventFederationDeliveryStatus::DeadLetter->value] ?? 0),
            'oldest_outstanding_at' => self::nullableText($outstanding->min('created_at')),
            'generated_at' => now()->utc()->toIso8601String(),
        ];
    }

    /** @param array<string,int> $counts @param list<array<string,mixed>> $partners */
    private function health(int $configuredPartners, array $counts, array $partners): string
    {
        if (($counts[EventFederationDeliveryStatus::DeadLetter->value] ?? 0) > 0) {
            return 'degraded';
        }
        if (($counts[EventFederationDeliveryStatus::Retry->value] ?? 0) > 0
            || ($counts[EventFederationDeliveryStatus::Processing->value] ?? 0) > 0
            || ($counts[EventFederationDeliveryStatus::Pending->value] ?? 0) > 0) {
            return 'delivering';
        }
        if ($partners !== [] && collect($partners)->every(
            static fn (array $partner): bool => $partner['action'] === 'tombstone',
        )) {
            return 'withdrawn';
        }

        return $configuredPartners === 0 ? 'not_configured' : 'healthy';
    }

    private static function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
