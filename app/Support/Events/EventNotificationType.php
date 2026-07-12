<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use Illuminate\Database\Eloquent\Builder;

/**
 * Canonical classifier for event-related bell notification types.
 *
 * New event notification producers use the event_* namespace. The historical
 * new_event_created alias remains readable so existing rows do not disappear
 * from the Events category during the migration.
 */
final class EventNotificationType
{
    public static function matches(?string $type): bool
    {
        $normalized = strtolower(trim((string) $type));

        return $normalized === 'event'
            || str_starts_with($normalized, 'event_')
            || $normalized === 'new_event_created';
    }

    /**
     * Apply the same event type classification at the database boundary.
     */
    public static function applyTo(Builder $query): Builder
    {
        return $query->where(static function (Builder $eventTypes): void {
            $eventTypes
                ->where('type', 'event')
                ->orWhere('type', 'like', 'event\\_%')
                ->orWhere('type', 'new_event_created');
        });
    }
}
