<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Enums\EventOperationalState;
use App\Enums\EventPublicationState;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Throwable;

/** One fail-closed registrability decision shared by writers and read models. */
final class EventRegistrationAvailability
{
    public const AVAILABLE = 'available';
    public const CONCRETE_OCCURRENCE_REQUIRED = 'concrete_occurrence_required';
    public const LIFECYCLE_UNAVAILABLE = 'lifecycle_unavailable';
    public const STARTED = 'started';

    public static function evaluate(Event $event, ?Carbon $now = null): string
    {
        if ((int) $event->getKey() <= 0
            || (bool) $event->getRawOriginal('is_recurring_template')) {
            return self::CONCRETE_OCCURRENCE_REQUIRED;
        }

        try {
            $lifecycle = EventLifecycleCompatibility::resolve(
                self::stateString($event->getRawOriginal('publication_status')),
                self::stateString($event->getRawOriginal('operational_status')),
                self::stateString($event->getRawOriginal('status')),
            );
        } catch (Throwable) {
            return self::LIFECYCLE_UNAVAILABLE;
        }
        if ($lifecycle['publication'] !== EventPublicationState::Published
            || ! in_array($lifecycle['operational'], [
                EventOperationalState::Scheduled,
                EventOperationalState::Postponed,
            ], true)) {
            return self::LIFECYCLE_UNAVAILABLE;
        }

        $start = self::carbon($event->getRawOriginal('start_time'));
        $now ??= now();
        if ($start === null || ! $start->isAfter($now)) {
            return self::STARTED;
        }

        return self::AVAILABLE;
    }

    public static function isRegistrable(Event $event, ?Carbon $now = null): bool
    {
        return self::evaluate($event, $now) === self::AVAILABLE;
    }

    private static function carbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'UTC');
        } catch (Throwable) {
            return null;
        }
    }

    private static function stateString(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        return $value === null ? null : strtolower(trim((string) $value));
    }
}
