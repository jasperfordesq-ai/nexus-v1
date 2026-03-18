<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * GoalReminderService — Eloquent-based service for goal reminders.
 *
 * Replaces the legacy DI wrapper that delegated to
 * \Nexus\Services\GoalReminderService.
 */
class GoalReminderService
{
    /**
     * Get reminder settings for a goal.
     */
    public function getReminder(int $goalId, int $userId): ?array
    {
        $row = DB::table('goal_reminders')
            ->where('goal_id', $goalId)
            ->where('user_id', $userId)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Set or update a reminder.
     */
    public function setReminder(int $goalId, int $userId, array $data): array
    {
        $existing = DB::table('goal_reminders')
            ->where('goal_id', $goalId)
            ->where('user_id', $userId)
            ->first();

        $values = [
            'frequency'  => $data['frequency'] ?? 'weekly',
            'enabled'    => $data['enabled'] ?? true,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('goal_reminders')
                ->where('id', $existing->id)
                ->update($values);
            $id = $existing->id;
        } else {
            $id = DB::table('goal_reminders')->insertGetId(array_merge($values, [
                'goal_id'    => $goalId,
                'user_id'    => $userId,
                'created_at' => now(),
            ]));
        }

        return (array) DB::table('goal_reminders')->where('id', $id)->where('tenant_id', TenantContext::getId())->first();
    }

    /**
     * Delete a reminder.
     */
    public function deleteReminder(int $goalId, int $userId): bool
    {
        return (bool) DB::table('goal_reminders')
            ->where('goal_id', $goalId)
            ->where('user_id', $userId)
            ->delete();
    }
}
