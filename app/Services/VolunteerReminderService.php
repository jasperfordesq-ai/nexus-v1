<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
    public static function sendReminders(int $tenantId, int $opportunityId): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }

    /**
     * Delegates to legacy VolunteerReminderService::scheduleReminder().
     */
    public static function scheduleReminder(int $tenantId, int $opportunityId, string $datetime): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy VolunteerReminderService::cancelReminder().
     */
    public static function cancelReminder(int $tenantId, int $reminderId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy VolunteerReminderService::getSettings().
     */
    public static function getSettings(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy VolunteerReminderService::updateSetting().
     */
    public static function updateSetting(string $type, array $data): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}
