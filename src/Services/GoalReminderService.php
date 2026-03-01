<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * GoalReminderService - Business logic for goal reminders
 *
 * Allows users to set periodic reminders for their goals. Provides a
 * cron-compatible method to send due reminders via notifications.
 *
 * @package Nexus\Services
 */
class GoalReminderService
{
    /** @var array Collected errors */
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    private static function clearErrors(): void
    {
        self::$errors = [];
    }

    private static function addError(string $code, string $message, ?string $field = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }
        self::$errors[] = $error;
    }

    /**
     * Set or update a reminder for a goal
     *
     * @param int $goalId
     * @param int $userId
     * @param array $data Keys: frequency, enabled
     * @return array|null Reminder data on success
     */
    public static function setReminder(int $goalId, int $userId, array $data): ?array
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Verify goal exists and user owns it
        $goal = Database::query(
            "SELECT id, user_id, status FROM goals WHERE id = ? AND tenant_id = ?",
            [$goalId, $tenantId]
        )->fetch();

        if (!$goal) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Goal not found');
            return null;
        }

        if ((int)$goal['user_id'] !== $userId) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only set reminders for your own goals');
            return null;
        }

        if ($goal['status'] === 'completed') {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Cannot set reminders for completed goals');
            return null;
        }

        $frequency = $data['frequency'] ?? 'weekly';
        $enabled = isset($data['enabled']) ? (bool)$data['enabled'] : true;

        $validFrequencies = ['daily', 'weekly', 'biweekly', 'monthly'];
        if (!in_array($frequency, $validFrequencies, true)) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid frequency. Must be one of: ' . implode(', ', $validFrequencies), 'frequency');
            return null;
        }

        $nextReminderAt = $enabled ? self::calculateNextReminder($frequency) : null;

        try {
            // Upsert: insert or update on duplicate
            $existing = Database::query(
                "SELECT id FROM goal_reminders WHERE goal_id = ? AND user_id = ?",
                [$goalId, $userId]
            )->fetch();

            if ($existing) {
                Database::query(
                    "UPDATE goal_reminders SET frequency = ?, enabled = ?, next_reminder_at = ? WHERE goal_id = ? AND user_id = ?",
                    [$frequency, $enabled ? 1 : 0, $nextReminderAt, $goalId, $userId]
                );
            } else {
                Database::query(
                    "INSERT INTO goal_reminders (goal_id, user_id, tenant_id, frequency, enabled, next_reminder_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$goalId, $userId, $tenantId, $frequency, $enabled ? 1 : 0, $nextReminderAt]
                );
            }

            return self::getReminder($goalId, $userId);
        } catch (\Throwable $e) {
            error_log("Goal reminder set failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to set reminder');
            return null;
        }
    }

    /**
     * Get the reminder settings for a goal/user
     *
     * @param int $goalId
     * @param int $userId
     * @return array|null
     */
    public static function getReminder(int $goalId, int $userId): ?array
    {
        $reminder = Database::query(
            "SELECT * FROM goal_reminders WHERE goal_id = ? AND user_id = ?",
            [$goalId, $userId]
        )->fetch();

        if (!$reminder) {
            return null;
        }

        return [
            'id' => (int)$reminder['id'],
            'goal_id' => (int)$reminder['goal_id'],
            'frequency' => $reminder['frequency'],
            'enabled' => (bool)$reminder['enabled'],
            'next_reminder_at' => $reminder['next_reminder_at'],
            'last_sent_at' => $reminder['last_sent_at'],
        ];
    }

    /**
     * Delete a reminder for a goal
     *
     * @param int $goalId
     * @param int $userId
     * @return bool
     */
    public static function deleteReminder(int $goalId, int $userId): bool
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM goal_reminders WHERE goal_id = ? AND user_id = ? AND tenant_id = ?",
                [$goalId, $userId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Goal reminder delete failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete reminder');
            return false;
        }
    }

    /**
     * Send all due reminders (cron-compatible)
     *
     * Called by a scheduled task. Finds all enabled reminders where
     * next_reminder_at <= NOW() and sends notifications.
     *
     * @return int Number of reminders sent
     */
    public static function sendDueReminders(): int
    {
        $dueReminders = Database::query(
            "SELECT r.*, g.title as goal_title, g.status as goal_status,
                    g.current_value, g.target_value, g.tenant_id
             FROM goal_reminders r
             JOIN goals g ON r.goal_id = g.id
             WHERE r.enabled = 1
               AND r.next_reminder_at <= NOW()
               AND g.status = 'active'
             LIMIT 100"
        )->fetchAll();

        $sentCount = 0;

        foreach ($dueReminders as $reminder) {
            try {
                // Calculate progress
                $progress = 0;
                if ((float)$reminder['target_value'] > 0) {
                    $progress = round(((float)$reminder['current_value'] / (float)$reminder['target_value']) * 100, 1);
                }

                // Create a notification
                Database::query(
                    "INSERT INTO notifications (tenant_id, user_id, type, title, message, link, created_at)
                     VALUES (?, ?, 'goal_reminder', ?, ?, ?, NOW())",
                    [
                        $reminder['tenant_id'],
                        $reminder['user_id'],
                        'Goal Reminder',
                        sprintf('Time to check in on "%s" (%s%% complete)', $reminder['goal_title'], $progress),
                        '/goals/' . $reminder['goal_id'],
                    ]
                );

                // Update the reminder: advance next_reminder_at and set last_sent_at
                $nextReminderAt = self::calculateNextReminder($reminder['frequency']);
                Database::query(
                    "UPDATE goal_reminders SET next_reminder_at = ?, last_sent_at = NOW() WHERE id = ?",
                    [$nextReminderAt, $reminder['id']]
                );

                $sentCount++;
            } catch (\Throwable $e) {
                error_log("Failed to send goal reminder {$reminder['id']}: " . $e->getMessage());
            }
        }

        return $sentCount;
    }

    /**
     * Calculate the next reminder datetime based on frequency
     *
     * @param string $frequency
     * @return string Datetime string
     */
    private static function calculateNextReminder(string $frequency): string
    {
        $interval = match ($frequency) {
            'daily' => '+1 day',
            'weekly' => '+1 week',
            'biweekly' => '+2 weeks',
            'monthly' => '+1 month',
            default => '+1 week',
        };

        return date('Y-m-d H:i:s', strtotime($interval));
    }
}
