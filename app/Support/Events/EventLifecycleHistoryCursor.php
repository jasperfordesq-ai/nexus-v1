<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Exceptions\EventLifecycleHistoryException;
use JsonException;

/** Event-bound, versioned opaque cursor for immutable lifecycle history. */
final class EventLifecycleHistoryCursor
{
    private const VERSION = 1;

    public static function encode(int $eventId, int $historyId): string
    {
        if ($eventId <= 0 || $historyId <= 0) {
            throw new EventLifecycleHistoryException('event_lifecycle_history_cursor_invalid');
        }

        try {
            $json = json_encode([
                'v' => self::VERSION,
                'event_id' => $eventId,
                'history_id' => $historyId,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new EventLifecycleHistoryException('event_lifecycle_history_cursor_invalid');
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public static function decode(string $cursor, int $eventId): int
    {
        $cursor = trim($cursor);
        if ($eventId <= 0
            || $cursor === ''
            || strlen($cursor) > 256
            || preg_match('/^[A-Za-z0-9_-]+$/D', $cursor) !== 1) {
            throw new EventLifecycleHistoryException('event_lifecycle_history_cursor_invalid');
        }

        $padding = (4 - (strlen($cursor) % 4)) % 4;
        $decoded = base64_decode(
            strtr($cursor, '-_', '+/') . str_repeat('=', $padding),
            true,
        );
        if (! is_string($decoded) || $decoded === '') {
            throw new EventLifecycleHistoryException('event_lifecycle_history_cursor_invalid');
        }

        try {
            $payload = json_decode($decoded, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new EventLifecycleHistoryException('event_lifecycle_history_cursor_invalid');
        }
        if (! is_array($payload)
            || array_keys($payload) !== ['v', 'event_id', 'history_id']
            || ($payload['v'] ?? null) !== self::VERSION
            || ! is_int($payload['event_id'] ?? null)
            || ! is_int($payload['history_id'] ?? null)
            || $payload['event_id'] !== $eventId
            || $payload['history_id'] <= 0
            || ! hash_equals(self::encode($eventId, $payload['history_id']), $cursor)) {
            throw new EventLifecycleHistoryException('event_lifecycle_history_cursor_invalid');
        }

        return $payload['history_id'];
    }
}
