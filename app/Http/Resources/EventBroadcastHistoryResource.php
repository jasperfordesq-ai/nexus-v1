<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EventBroadcastHistory;
use BackedEnum;
use DateTimeInterface;

/** Append-only lifecycle projection; actor and recipient identities are excluded. */
final class EventBroadcastHistoryResource
{
    /** @return array<string,mixed> */
    public static function fromModel(EventBroadcastHistory $history): array
    {
        return [
            'id' => (int) $history->id,
            'version' => (int) $history->broadcast_version,
            'action' => self::enum($history->action),
            'from_status' => self::enum($history->from_status),
            'to_status' => self::enum($history->to_status),
            'metadata' => self::metadata($history->metadata),
            'created_at' => self::timestamp($history->created_at),
        ];
    }

    private static function enum(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /** @return array<string,mixed> */
    private static function metadata(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $allowed = [
            'contract_version',
            'variant',
            'segments',
            'channels',
            'recipient_count',
            'delivery_count',
            'segment_counts',
            'scheduled_at',
            'reason_recorded',
            'cancelled_delivery_count',
            'reset_delivery_count',
            'delivered_count',
            'suppressed_count',
            'dead_letter_count',
        ];

        return array_intersect_key($value, array_flip($allowed));
    }

    private static function timestamp(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface
            ? $value->format(DATE_ATOM)
            : (is_string($value) && trim($value) !== '' ? $value : null);
    }
}
