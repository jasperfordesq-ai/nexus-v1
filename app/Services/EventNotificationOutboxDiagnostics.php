<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Payload-free operational snapshot for Event outbox dashboards and CLI health checks. */
final class EventNotificationOutboxDiagnostics
{
    /** @return array<string,mixed> */
    public function snapshot(?int $tenantId = null): array
    {
        if (! Schema::hasTable('event_domain_outbox')
            || ! Schema::hasTable('event_notification_deliveries')) {
            return [
                'schema_available' => false,
                'channel_configuration' => EventNotificationChannelConfiguration::inspect(),
            ];
        }

        $allOutbox = DB::table('event_domain_outbox')
            ->when($tenantId !== null, static fn ($query) => $query->where('tenant_id', $tenantId));
        $outbox = clone $allOutbox;
        EventNotificationOutboxScope::apply($outbox);
        $deliveries = DB::table('event_notification_deliveries')
            ->when($tenantId !== null, static fn ($query) => $query->where('tenant_id', $tenantId));
        $outboxCounts = (clone $outbox)
            ->select('status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
        $deliveryCounts = (clone $deliveries)
            ->select('status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
        $now = now();
        $oldest = (clone $outbox)
            ->where(static function ($query) use ($now): void {
                $query->where('status', 'processing')
                    ->orWhere(static function ($pending) use ($now): void {
                        $pending->where('status', 'pending')
                            ->where(static function ($available) use ($now): void {
                                $available->whereNull('available_at')->orWhere('available_at', '<=', $now);
                            })
                            ->where(static function ($retry) use ($now): void {
                                $retry->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now);
                            });
                    });
            })
            ->min('created_at');
        $oldestAge = $oldest !== null ? max(0, now()->diffInSeconds($oldest, false) * -1) : 0;
        $staleMinutes = max(1, (int) config('events.notification_delivery.stale_claim_minutes', 10));

        return [
            'schema_available' => true,
            'consumer_enabled' => EventNotificationDeliveryModeResolver::consumerEnabled(),
            'channel_configuration' => EventNotificationChannelConfiguration::inspect(),
            'tenant_id' => $tenantId,
            'outbox' => $outboxCounts,
            'deliveries' => $deliveryCounts,
            'oldest_deliverable_age_seconds' => (int) $oldestAge,
            'stale_processing' => (clone $outbox)
                ->where('status', 'processing')
                ->where('claimed_at', '<', now()->subMinutes($staleMinutes))
                ->count(),
            'dead_lettered' => (int) ($outboxCounts['dead_letter'] ?? 0),
            'terminal_delivery_failures' => (int) ($deliveryCounts['failed_terminal'] ?? 0),
            'excluded_domain_facts' => max(0, (clone $allOutbox)->count() - (clone $outbox)->count()),
        ];
    }
}
