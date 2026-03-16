<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GoalReminderService — Laravel DI wrapper for legacy \Nexus\Services\GoalReminderService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GoalReminderService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GoalReminderService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\GoalReminderService::getErrors();
    }

    /**
     * Delegates to legacy GoalReminderService::setReminder().
     */
    public function setReminder(int $goalId, int $userId, array $data): ?array
    {
        return \Nexus\Services\GoalReminderService::setReminder($goalId, $userId, $data);
    }

    /**
     * Delegates to legacy GoalReminderService::getReminder().
     */
    public function getReminder(int $goalId, int $userId): ?array
    {
        return \Nexus\Services\GoalReminderService::getReminder($goalId, $userId);
    }

    /**
     * Delegates to legacy GoalReminderService::deleteReminder().
     */
    public function deleteReminder(int $goalId, int $userId): bool
    {
        return \Nexus\Services\GoalReminderService::deleteReminder($goalId, $userId);
    }

    /**
     * Delegates to legacy GoalReminderService::sendDueReminders().
     */
    public function sendDueReminders(): int
    {
        return \Nexus\Services\GoalReminderService::sendDueReminders();
    }
}
