<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * EventReminderService — Laravel DI wrapper for legacy \Nexus\Services\EventReminderService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class EventReminderService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy EventReminderService::scheduleReminder().
     */
    public function scheduleReminder(int $tenantId, int $eventId, string $remindAt): bool
    {
        return \Nexus\Services\EventReminderService::scheduleReminder($tenantId, $eventId, $remindAt);
    }

    /**
     * Delegates to legacy EventReminderService::sendDueReminders().
     */
    public function sendDueReminders(int $tenantId): int
    {
        return \Nexus\Services\EventReminderService::sendDueReminders($tenantId);
    }

    /**
     * Delegates to legacy EventReminderService::cancelReminder().
     */
    public function cancelReminder(int $tenantId, int $eventId): bool
    {
        return \Nexus\Services\EventReminderService::cancelReminder($tenantId, $eventId);
    }
}
