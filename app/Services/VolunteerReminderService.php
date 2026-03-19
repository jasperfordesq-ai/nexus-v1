<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * VolunteerReminderService � Laravel DI wrapper for legacy \Nexus\Services\VolunteerReminderService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class VolunteerReminderService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VolunteerReminderService::sendReminders().
     */
    public function sendReminders(int $tenantId, int $opportunityId): int
    {
        return \Nexus\Services\VolunteerReminderService::sendReminders($tenantId, $opportunityId);
    }

    /**
     * Delegates to legacy VolunteerReminderService::scheduleReminder().
     */
    public function scheduleReminder(int $tenantId, int $opportunityId, string $datetime): bool
    {
        return \Nexus\Services\VolunteerReminderService::scheduleReminder($tenantId, $opportunityId, $datetime);
    }

    /**
     * Delegates to legacy VolunteerReminderService::cancelReminder().
     */
    public function cancelReminder(int $tenantId, int $reminderId): bool
    {
        return \Nexus\Services\VolunteerReminderService::cancelReminder($tenantId, $reminderId);
    }

    /**
     * Delegates to legacy VolunteerReminderService::getSettings().
     */
    public function getSettings(): array
    {
        return \Nexus\Services\VolunteerReminderService::getSettings();
    }

    /**
     * Delegates to legacy VolunteerReminderService::updateSetting().
     */
    public function updateSetting(string $type, array $data): bool
    {
        return \Nexus\Services\VolunteerReminderService::updateSetting($type, $data);
    }
}
