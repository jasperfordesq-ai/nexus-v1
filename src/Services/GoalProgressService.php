<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * GoalProgressService - Progress history and timeline tracking for goals
 *
 * Records and retrieves a chronological history of all progress changes,
 * milestones reached, check-ins, and other significant events for a goal.
 *
 * @package Nexus\Services
 */
class GoalProgressService
{
    /**
     * Log a progress event for a goal
     *
     * @param int $goalId
     * @param int $tenantId
     * @param string $eventType One of: progress_update, milestone_reached, checkin, status_change, buddy_joined, created, completed
     * @param string|null $oldValue
     * @param string|null $newValue
     * @param int|null $createdBy User ID who triggered the event
     * @param array|null $metadata Additional metadata as associative array
     */
    public static function logEvent(
        int $goalId,
        int $tenantId,
        string $eventType,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?int $createdBy = null,
        ?array $metadata = null
    ): void {
        try {
            Database::query(
                "INSERT INTO goal_progress_log (goal_id, tenant_id, event_type, old_value, new_value, metadata, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $goalId,
                    $tenantId,
                    $eventType,
                    $oldValue,
                    $newValue,
                    $metadata !== null ? json_encode($metadata) : null,
                    $createdBy,
                ]
            );
        } catch (\Throwable $e) {
            // Progress logging should never break the main flow
            error_log("GoalProgressService::logEvent failed: " . $e->getMessage());
        }
    }

    /**
     * Get the full progress history for a goal
     *
     * Returns a chronological list of all events including progress changes,
     * milestones reached, check-ins, status changes, etc.
     *
     * @param int $goalId
     * @param array $filters Keys: cursor, limit, event_type
     * @return array ['items' => [...], 'cursor' => ?string, 'has_more' => bool]
     */
    public static function getProgressHistory(int $goalId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 50;
        $cursor = $filters['cursor'] ?? null;
        $eventType = $filters['event_type'] ?? null;

        $params = [$goalId, $tenantId];
        $where = ["pl.goal_id = ?", "pl.tenant_id = ?"];

        if ($eventType) {
            $where[] = "pl.event_type = ?";
            $params[] = $eventType;
        }

        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "pl.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $sql = "
            SELECT
                pl.*,
                u.first_name as user_first_name,
                u.last_name as user_last_name,
                u.avatar_url as user_avatar
            FROM goal_progress_log pl
            LEFT JOIN users u ON pl.created_by = u.id
            WHERE {$whereClause}
            ORDER BY pl.created_at DESC, pl.id DESC
            LIMIT ?
        ";

        $events = Database::query($sql, $params)->fetchAll();

        $hasMore = count($events) > $limit;
        if ($hasMore) {
            array_pop($events);
        }

        $nextCursor = null;
        if ($hasMore && !empty($events)) {
            $lastItem = end($events);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        $items = array_map(function ($e) {
            $metadata = null;
            if (!empty($e['metadata'])) {
                $metadata = json_decode($e['metadata'], true);
            }

            return [
                'id' => (int)$e['id'],
                'goal_id' => (int)$e['goal_id'],
                'event_type' => $e['event_type'],
                'old_value' => $e['old_value'],
                'new_value' => $e['new_value'],
                'metadata' => $metadata,
                'created_at' => $e['created_at'],
                'user' => $e['created_by'] ? [
                    'id' => (int)$e['created_by'],
                    'name' => trim(($e['user_first_name'] ?? '') . ' ' . ($e['user_last_name'] ?? '')),
                    'avatar_url' => $e['user_avatar'] ?? null,
                ] : null,
            ];
        }, $events);

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a summary of progress for a goal (for the detail page)
     *
     * @param int $goalId
     * @return array Summary with total events, last activity, etc.
     */
    public static function getSummary(int $goalId): array
    {
        $tenantId = TenantContext::getId();

        $counts = Database::query(
            "SELECT event_type, COUNT(*) as count
             FROM goal_progress_log
             WHERE goal_id = ? AND tenant_id = ?
             GROUP BY event_type",
            [$goalId, $tenantId]
        )->fetchAll();

        $lastActivity = Database::query(
            "SELECT created_at FROM goal_progress_log
             WHERE goal_id = ? AND tenant_id = ?
             ORDER BY created_at DESC LIMIT 1",
            [$goalId, $tenantId]
        )->fetch();

        $checkinCount = Database::query(
            "SELECT COUNT(*) as count FROM goal_checkins
             WHERE goal_id = ? AND tenant_id = ?",
            [$goalId, $tenantId]
        )->fetch();

        $eventCounts = [];
        foreach ($counts as $row) {
            $eventCounts[$row['event_type']] = (int)$row['count'];
        }

        return [
            'total_events' => array_sum($eventCounts),
            'event_counts' => $eventCounts,
            'total_checkins' => (int)($checkinCount['count'] ?? 0),
            'last_activity' => $lastActivity ? $lastActivity['created_at'] : null,
        ];
    }
}
