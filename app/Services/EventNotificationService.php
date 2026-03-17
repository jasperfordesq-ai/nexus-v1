<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * EventNotificationService � Laravel DI wrapper for legacy \Nexus\Services\EventNotificationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class EventNotificationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy EventNotificationService::notifyAttendees().
     */
    public function notifyAttendees(int $tenantId, int $eventId, string $message): int
    {
        return \Nexus\Services\EventNotificationService::notifyAttendees($tenantId, $eventId, $message);
    }

    /**
     * Delegates to legacy EventNotificationService::sendReminder().
     */
    public function sendReminder(int $tenantId, int $eventId): int
    {
        return \Nexus\Services\EventNotificationService::sendReminder($tenantId, $eventId);
    }

    /**
     * Delegates to legacy EventNotificationService::notifyCancellation().
     */
    public function notifyCancellation(int $tenantId, int $eventId, ?string $reason = null): int
    {
        return \Nexus\Services\EventNotificationService::notifyCancellation($tenantId, $eventId, $reason);
    }

    /**
     * Notify attendees that an event has been updated.
     */
    public function notifyEventUpdated(int $eventId, array $changes): void
    {
        \Nexus\Services\EventNotificationService::notifyEventUpdated($eventId, $changes);
    }

    /**
     * Notify about an RSVP action on an event.
     */
    public function notifyRsvp(int $eventId, int $userId, string $status): void
    {
        \Nexus\Services\EventNotificationService::notifyRsvp($eventId, $userId, $status);
    }
}
