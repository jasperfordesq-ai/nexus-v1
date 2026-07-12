<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

/** Explicitly excludes payloads, hashes, claims, credentials, and raw errors. */
final class EventFederationStatusResource
{
    /** @param array<string,mixed> $summary @return array<string,mixed> */
    public static function fromSummary(array $summary): array
    {
        $counts = [];
        foreach (['pending', 'retry', 'processing', 'delivered', 'dead_letter'] as $status) {
            $counts[$status] = max(0, (int) (($summary['counts'][$status] ?? 0)));
        }
        $partners = [];
        foreach (($summary['partners'] ?? []) as $partner) {
            if (! is_array($partner)) {
                continue;
            }
            $partners[] = [
                'partner_id' => max(0, (int) ($partner['partner_id'] ?? 0)),
                'partner_name' => self::text($partner['partner_name'] ?? null),
                'partner_status' => self::text($partner['partner_status'] ?? null) ?? 'removed',
                'events_enabled' => (bool) ($partner['events_enabled'] ?? false),
                'action' => self::text($partner['action'] ?? null),
                'delivery_status' => self::text($partner['delivery_status'] ?? null),
                'attempts' => max(0, (int) ($partner['attempts'] ?? 0)),
                'max_attempts' => max(1, (int) ($partner['max_attempts'] ?? 1)),
                'aggregate_version' => max(0, (int) ($partner['aggregate_version'] ?? 0)),
                'calendar_version' => max(0, (int) ($partner['calendar_version'] ?? 0)),
                'available_at' => self::text($partner['available_at'] ?? null),
                'next_attempt_at' => self::text($partner['next_attempt_at'] ?? null),
                'last_attempt_at' => self::text($partner['last_attempt_at'] ?? null),
                'delivered_at' => self::text($partner['delivered_at'] ?? null),
                'dead_lettered_at' => self::text($partner['dead_lettered_at'] ?? null),
                'error_code' => self::text($partner['error_code'] ?? null),
            ];
        }

        return [
            'contract_version' => 1,
            'event_id' => max(0, (int) ($summary['event_id'] ?? 0)),
            'federation_version' => max(1, (int) ($summary['federation_version'] ?? 1)),
            'visibility' => self::text($summary['visibility'] ?? null) ?? 'none',
            'configured_partners' => max(0, (int) ($summary['configured_partners'] ?? 0)),
            'recipient_partners' => max(0, (int) ($summary['recipient_partners'] ?? 0)),
            'health' => self::text($summary['health'] ?? null) ?? 'not_configured',
            'counts' => $counts,
            'partners' => $partners,
            'generated_at' => self::text($summary['generated_at'] ?? null),
        ];
    }

    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
