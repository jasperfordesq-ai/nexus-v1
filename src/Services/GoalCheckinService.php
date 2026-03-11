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
 * GoalCheckinService - Business logic for goal check-ins
 *
 * Handles creating and retrieving periodic check-in records for goals.
 * Each check-in captures the user's current progress percentage, a text
 * note, and an optional mood indicator.
 *
 * @package Nexus\Services
 */
class GoalCheckinService
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
     * Create a check-in for a goal
     *
     * @param int $goalId
     * @param int $userId
     * @param array $data Keys: progress_percent, note, mood
     * @return int|null Check-in ID on success
     */
    public static function create(int $goalId, int $userId, array $data): ?int
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Verify goal exists and belongs to tenant
        $goal = Database::query(
            "SELECT id, user_id, tenant_id FROM goals WHERE id = ? AND tenant_id = ?",
            [$goalId, $tenantId]
        )->fetch();

        if (!$goal) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Goal not found');
            return null;
        }

        // Must be goal owner or buddy
        if ((int)$goal['user_id'] !== $userId) {
            $isBuddy = Database::query(
                "SELECT id FROM goals WHERE id = ? AND mentor_id = ?",
                [$goalId, $userId]
            )->fetch();

            if (!$isBuddy) {
                self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only check in on your own goals or goals you buddy');
                return null;
            }
        }

        // Accept both progress_percent and progress_value (frontend sends progress_value)
        $progressPercent = isset($data['progress_percent']) ? (float)$data['progress_percent']
            : (isset($data['progress_value']) ? (float)$data['progress_value'] : null);
        $note = trim($data['note'] ?? '');
        $mood = $data['mood'] ?? null;

        // Validate mood
        $validMoods = ['great', 'good', 'neutral', 'okay', 'struggling', 'stuck', 'motivated', 'grateful'];
        if ($mood !== null && !in_array($mood, $validMoods, true)) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid mood value. Must be one of: ' . implode(', ', $validMoods), 'mood');
            return null;
        }

        // Validate progress
        if ($progressPercent !== null && ($progressPercent < 0 || $progressPercent > 100)) {
            self::addError(ApiErrorCodes::VALIDATION_OUT_OF_RANGE, 'Progress must be between 0 and 100', 'progress_percent');
            return null;
        }

        // Must have at least a note or progress update
        if (empty($note) && $progressPercent === null && $mood === null) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'At least one of note, progress_percent, or mood is required');
            return null;
        }

        try {
            Database::query(
                "INSERT INTO goal_checkins (goal_id, user_id, tenant_id, progress_percent, note, mood, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$goalId, $userId, $tenantId, $progressPercent, $note ?: null, $mood]
            );

            $checkinId = (int)Database::lastInsertId();

            // Update last_checkin_at on the goal
            Database::query(
                "UPDATE goals SET last_checkin_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$goalId, $tenantId]
            );

            // Log the check-in in progress history
            GoalProgressService::logEvent($goalId, $tenantId, 'checkin', null, $progressPercent !== null ? (string)$progressPercent . '%' : null, $userId, [
                'checkin_id' => $checkinId,
                'mood' => $mood,
                'note_preview' => mb_substr($note, 0, 100),
            ]);

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 3, 'Goal check-in');
                }
            } catch (\Throwable $e) {
                // Gamification is optional
            }

            return $checkinId;
        } catch (\Throwable $e) {
            error_log("Goal check-in creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create check-in');
            return null;
        }
    }

    /**
     * Get check-ins for a goal
     *
     * @param int $goalId
     * @param array $filters Keys: cursor, limit
     * @return array ['items' => [...], 'cursor' => ?string, 'has_more' => bool]
     */
    public static function getByGoalId(int $goalId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;

        $params = [$goalId, $tenantId];
        $where = ["c.goal_id = ?", "c.tenant_id = ?"];

        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "c.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $sql = "
            SELECT
                c.*,
                u.first_name as user_first_name,
                u.last_name as user_last_name,
                u.avatar_url as user_avatar
            FROM goal_checkins c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE {$whereClause}
            ORDER BY c.created_at DESC, c.id DESC
            LIMIT ?
        ";

        $checkins = Database::query($sql, $params)->fetchAll();

        $hasMore = count($checkins) > $limit;
        if ($hasMore) {
            array_pop($checkins);
        }

        $nextCursor = null;
        if ($hasMore && !empty($checkins)) {
            $lastItem = end($checkins);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        // Format each check-in
        $items = array_map(function ($c) {
            return [
                'id' => (int)$c['id'],
                'goal_id' => (int)$c['goal_id'],
                'progress_percent' => $c['progress_percent'] !== null ? (float)$c['progress_percent'] : null,
                'note' => $c['note'],
                'mood' => $c['mood'],
                'created_at' => $c['created_at'],
                'user' => [
                    'id' => (int)$c['user_id'],
                    'name' => trim(($c['user_first_name'] ?? '') . ' ' . ($c['user_last_name'] ?? '')),
                    'avatar_url' => $c['user_avatar'] ?? null,
                ],
            ];
        }, $checkins);

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }
}
