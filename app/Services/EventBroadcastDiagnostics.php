<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventBroadcastDeliveryStatus;
use App\Support\Events\EventBroadcastFoundationSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Identity-, content-, claim-, and provider-evidence-free operational snapshot. */
final class EventBroadcastDiagnostics
{
    /** @return array<string,mixed> */
    public function snapshot(?int $tenantId = null, ?int $eventId = null): array
    {
        foreach (EventBroadcastFoundationSupport::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                return ['schema_available' => false];
            }
        }
        $broadcasts = DB::table('event_broadcasts')
            ->when($tenantId !== null, static fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($eventId !== null, static fn ($query) => $query->where('event_id', $eventId));
        $deliveries = DB::table('event_broadcast_deliveries')
            ->when($tenantId !== null, static fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($eventId !== null, static fn ($query) => $query->where('event_id', $eventId));
        $lifecycle = (clone $broadcasts)
            ->select('status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
        $delivery = (clone $deliveries)
            ->select('status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
        $channels = (clone $deliveries)
            ->select('channel', 'status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('channel', 'status')
            ->get()
            ->reduce(static function (array $carry, object $row): array {
                $carry[(string) $row->channel][(string) $row->status] = (int) $row->aggregate;
                return $carry;
            }, []);
        ksort($channels);
        $oldest = (clone $deliveries)
            ->whereIn('status', [
                EventBroadcastDeliveryStatus::Pending->value,
                EventBroadcastDeliveryStatus::Retry->value,
                EventBroadcastDeliveryStatus::Processing->value,
            ])
            ->min('created_at');
        $staleMinutes = max(1, (int) config('events.broadcast.stale_claim_minutes', 10));

        return [
            'schema_available' => true,
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'lifecycle' => $lifecycle,
            'deliveries' => $delivery,
            'channels' => $channels,
            'oldest_active_age_seconds' => $oldest !== null
                ? max(0, now()->diffInSeconds($oldest, false) * -1)
                : 0,
            'stale_processing' => (clone $deliveries)
                ->where('status', EventBroadcastDeliveryStatus::Processing->value)
                ->where('claimed_at', '<', now()->subMinutes($staleMinutes))
                ->count(),
            'max_attempts' => max(1, (int) config('events.broadcast.max_attempts', 5)),
        ];
    }
}
