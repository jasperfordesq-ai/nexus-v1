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
 * TeamTaskService - Task management for project teams (groups)
 *
 * Full CRUD for tasks with assignment, status transitions, priority, and
 * due dates. Tasks are scoped to a group and tenant.
 *
 * @package Nexus\Services
 */
class TeamTaskService
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
     * List tasks for a group with optional filtering
     *
     * @param int $groupId
     * @param array $filters status, assigned_to, cursor, limit
     * @return array
     */
    public static function getTasks(int $groupId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 50;
        $cursor = $filters['cursor'] ?? null;

        $params = [$groupId, $tenantId];
        $where = ["t.group_id = ?", "t.tenant_id = ?"];

        if (!empty($filters['status']) && in_array($filters['status'], ['todo', 'in_progress', 'done'])) {
            $where[] = "t.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['assigned_to'])) {
            $where[] = "t.assigned_to = ?";
            $params[] = (int)$filters['assigned_to'];
        }

        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "t.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $items = Database::query(
            "SELECT t.*,
                    cu.first_name AS creator_first, cu.last_name AS creator_last,
                    au.first_name AS assignee_first, au.last_name AS assignee_last, au.avatar_url AS assignee_avatar
             FROM team_tasks t
             LEFT JOIN users cu ON t.created_by = cu.id
             LEFT JOIN users au ON t.assigned_to = au.id
             WHERE {$whereClause}
             ORDER BY
                CASE t.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
                t.due_date ASC,
                t.created_at DESC,
                t.id DESC
             LIMIT ?",
            $params
        )->fetchAll();

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        // Format
        foreach ($items as &$item) {
            $item = self::formatTask($item);
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single task by ID
     */
    public static function getById(int $taskId): ?array
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT t.*,
                    cu.first_name AS creator_first, cu.last_name AS creator_last,
                    au.first_name AS assignee_first, au.last_name AS assignee_last, au.avatar_url AS assignee_avatar
             FROM team_tasks t
             LEFT JOIN users cu ON t.created_by = cu.id
             LEFT JOIN users au ON t.assigned_to = au.id
             WHERE t.id = ? AND t.tenant_id = ?",
            [$taskId, $tenantId]
        )->fetch();

        if (!$row) {
            return null;
        }

        return self::formatTask($row);
    }

    /**
     * Create a new task
     *
     * @return int|null Task ID
     */
    public static function create(int $groupId, int $userId, array $data): ?int
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Must be group member
        if (!self::isGroupMember($groupId, $userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You must be a group member to create tasks');
            return null;
        }

        $title = trim($data['title'] ?? '');
        if (empty($title)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title is required', 'title');
            return null;
        }

        $description = !empty($data['description']) ? trim($data['description']) : null;
        $assignedTo = isset($data['assigned_to']) ? (int)$data['assigned_to'] : null;

        // Validate assignee is a group member
        if ($assignedTo !== null && !self::isGroupMember($groupId, $assignedTo)) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Assignee must be a group member', 'assigned_to');
            return null;
        }

        $status = $data['status'] ?? 'todo';
        if (!in_array($status, ['todo', 'in_progress', 'done'])) {
            $status = 'todo';
        }

        $priority = $data['priority'] ?? 'medium';
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
            $priority = 'medium';
        }

        $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;

        try {
            Database::query(
                "INSERT INTO team_tasks (group_id, tenant_id, title, description, assigned_to, status, priority, due_date, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$groupId, $tenantId, $title, $description, $assignedTo, $status, $priority, $dueDate, $userId]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Task creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create task');
            return null;
        }
    }

    /**
     * Update a task
     */
    public static function update(int $taskId, int $userId, array $data): bool
    {
        self::clearErrors();

        $task = self::getById($taskId);
        if (!$task) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Task not found');
            return false;
        }

        $groupId = (int)$task['group_id'];

        // Must be group member
        if (!self::isGroupMember($groupId, $userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You must be a group member to update tasks');
            return false;
        }

        $tenantId = TenantContext::getId();
        $updates = [];
        $params = [];

        if (isset($data['title'])) {
            $title = trim($data['title']);
            if (empty($title)) {
                self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title cannot be empty', 'title');
                return false;
            }
            $updates[] = "title = ?";
            $params[] = $title;
        }

        if (array_key_exists('description', $data)) {
            $updates[] = "description = ?";
            $params[] = !empty($data['description']) ? trim($data['description']) : null;
        }

        if (array_key_exists('assigned_to', $data)) {
            $assignedTo = $data['assigned_to'] !== null ? (int)$data['assigned_to'] : null;
            if ($assignedTo !== null && !self::isGroupMember($groupId, $assignedTo)) {
                self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Assignee must be a group member', 'assigned_to');
                return false;
            }
            $updates[] = "assigned_to = ?";
            $params[] = $assignedTo;
        }

        if (isset($data['status'])) {
            $status = $data['status'];
            if (!in_array($status, ['todo', 'in_progress', 'done'])) {
                self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid status', 'status');
                return false;
            }
            $updates[] = "status = ?";
            $params[] = $status;

            // Auto-set completed_at
            if ($status === 'done') {
                $updates[] = "completed_at = NOW()";
            } elseif ($task['status'] === 'done' && $status !== 'done') {
                $updates[] = "completed_at = NULL";
            }
        }

        if (isset($data['priority'])) {
            $priority = $data['priority'];
            if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
                self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid priority', 'priority');
                return false;
            }
            $updates[] = "priority = ?";
            $params[] = $priority;
        }

        if (array_key_exists('due_date', $data)) {
            $updates[] = "due_date = ?";
            $params[] = !empty($data['due_date']) ? $data['due_date'] : null;
        }

        if (empty($updates)) {
            return true;
        }

        $params[] = $taskId;
        $params[] = $tenantId;

        try {
            Database::query(
                "UPDATE team_tasks SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
                $params
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Task update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update task');
            return false;
        }
    }

    /**
     * Delete a task
     */
    public static function delete(int $taskId, int $userId): bool
    {
        self::clearErrors();

        $task = self::getById($taskId);
        if (!$task) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Task not found');
            return false;
        }

        $isCreator = (int)$task['created_by'] === $userId;
        $isAdmin = self::isAdmin($userId);

        if (!$isCreator && !$isAdmin) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only the creator or an admin can delete tasks');
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM team_tasks WHERE id = ? AND tenant_id = ?",
                [$taskId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Task deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete task');
            return false;
        }
    }

    /**
     * Get task statistics for a group
     */
    public static function getStats(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        $stats = Database::query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) AS todo_count,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done_count
             FROM team_tasks
             WHERE group_id = ? AND tenant_id = ?",
            [$groupId, $tenantId]
        )->fetch();

        return [
            'total' => (int)($stats['total'] ?? 0),
            'todo' => (int)($stats['todo_count'] ?? 0),
            'in_progress' => (int)($stats['in_progress_count'] ?? 0),
            'done' => (int)($stats['done_count'] ?? 0),
        ];
    }

    /**
     * Format task row
     */
    private static function formatTask(array $task): array
    {
        $task['creator'] = [
            'id' => (int)$task['created_by'],
            'name' => trim(($task['creator_first'] ?? '') . ' ' . ($task['creator_last'] ?? '')),
        ];

        if ($task['assigned_to']) {
            $task['assignee'] = [
                'id' => (int)$task['assigned_to'],
                'name' => trim(($task['assignee_first'] ?? '') . ' ' . ($task['assignee_last'] ?? '')),
                'avatar_url' => $task['assignee_avatar'] ?? null,
            ];
        } else {
            $task['assignee'] = null;
        }

        unset(
            $task['creator_first'], $task['creator_last'],
            $task['assignee_first'], $task['assignee_last'], $task['assignee_avatar']
        );

        return $task;
    }

    private static function isGroupMember(int $groupId, int $userId): bool
    {
        $result = Database::query(
            "SELECT id FROM group_members WHERE group_id = ? AND user_id = ?",
            [$groupId, $userId]
        )->fetch();

        return !empty($result);
    }

    private static function isAdmin(int $userId): bool
    {
        $user = Database::query(
            "SELECT role FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, TenantContext::getId()]
        )->fetch();

        return $user && in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin']);
    }
}
