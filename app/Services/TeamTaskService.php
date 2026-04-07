<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * TeamTaskService — Native Eloquent/DB implementation for team task management.
 *
 * Manages tasks within groups (ideation challenge teams). Uses cursor-based pagination.
 */
class TeamTaskService
{
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get tasks for a group with optional filters and cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getTasks(int $groupId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 50;

        $query = DB::table('team_tasks')
            ->where('tenant_id', $tenantId)
            ->where('group_id', $groupId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        if (!empty($filters['cursor'])) {
            $query->where('id', '<', (int) $filters['cursor']);
        }

        $tasks = $query->orderByDesc('id')
            ->limit($limit + 1)
            ->get()
            ->toArray();

        $hasMore = count($tasks) > $limit;
        if ($hasMore) {
            array_pop($tasks);
        }

        $items = array_map(fn ($row) => (array) $row, $tasks);
        $cursor = !empty($items) ? (string) end($items)['id'] : null;

        return [
            'items' => $items,
            'cursor' => $cursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single task by ID (tenant-scoped).
     */
    public function getById(int $taskId): ?array
    {
        $tenantId = TenantContext::getId();

        $task = DB::table('team_tasks')
            ->where('id', $taskId)
            ->where('tenant_id', $tenantId)
            ->first();

        return $task ? (array) $task : null;
    }

    /**
     * Create a new task within a group.
     */
    public function create(int $groupId, int $userId, array $data): ?int
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $title = trim($data['title'] ?? '');
        if ($title === '') {
            $this->errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => __('svc_notifications_2.team_task.title_required'),
                'field' => 'title',
            ];
            return null;
        }

        // Validate status if provided
        $validStatuses = ['todo', 'in_progress', 'done'];
        $status = $data['status'] ?? 'todo';
        if (!in_array($status, $validStatuses, true)) {
            $this->errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => __('svc_notifications_2.team_task.invalid_status'),
                'field' => 'status',
            ];
            return null;
        }

        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        $priority = $data['priority'] ?? 'medium';
        if (!in_array($priority, $validPriorities, true)) {
            $this->errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => __('svc_notifications_2.team_task.invalid_priority'),
                'field' => 'priority',
            ];
            return null;
        }

        $now = now();

        $id = DB::table('team_tasks')->insertGetId([
            'group_id' => $groupId,
            'tenant_id' => $tenantId,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'assigned_to' => isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            'status' => $status,
            'priority' => $priority,
            'due_date' => $data['due_date'] ?? null,
            'created_by' => $userId,
            'created_at' => $now,
            'completed_at' => $status === 'done' ? $now : null,
        ]);

        return (int) $id;
    }

    /**
     * Update an existing task.
     */
    public function update(int $taskId, int $userId, array $data): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $task = DB::table('team_tasks')
            ->where('id', $taskId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$task) {
            $this->errors[] = [
                'code' => 'RESOURCE_NOT_FOUND',
                'message' => __('svc_notifications_2.team_task.task_not_found'),
            ];
            return false;
        }

        $update = [];

        if (array_key_exists('title', $data)) {
            $title = trim($data['title']);
            if ($title === '') {
                $this->errors[] = [
                    'code' => 'VALIDATION_ERROR',
                    'message' => __('svc_notifications_2.team_task.title_cannot_be_empty'),
                    'field' => 'title',
                ];
                return false;
            }
            $update['title'] = $title;
        }

        if (array_key_exists('description', $data)) {
            $update['description'] = $data['description'];
        }

        if (array_key_exists('assigned_to', $data)) {
            $update['assigned_to'] = $data['assigned_to'] !== null ? (int) $data['assigned_to'] : null;
        }

        if (array_key_exists('status', $data)) {
            $validStatuses = ['todo', 'in_progress', 'done'];
            if (!in_array($data['status'], $validStatuses, true)) {
                $this->errors[] = [
                    'code' => 'VALIDATION_ERROR',
                    'message' => __('svc_notifications_2.team_task.invalid_status'),
                    'field' => 'status',
                ];
                return false;
            }
            $update['status'] = $data['status'];

            // Set completed_at when transitioning to done
            if ($data['status'] === 'done' && $task->status !== 'done') {
                $update['completed_at'] = now();
            } elseif ($data['status'] !== 'done') {
                $update['completed_at'] = null;
            }
        }

        if (array_key_exists('priority', $data)) {
            $validPriorities = ['low', 'medium', 'high', 'urgent'];
            if (!in_array($data['priority'], $validPriorities, true)) {
                $this->errors[] = [
                    'code' => 'VALIDATION_ERROR',
                    'message' => __('svc_notifications_2.team_task.invalid_priority'),
                    'field' => 'priority',
                ];
                return false;
            }
            $update['priority'] = $data['priority'];
        }

        if (array_key_exists('due_date', $data)) {
            $update['due_date'] = $data['due_date'];
        }

        if (empty($update)) {
            return true; // Nothing to update is still success
        }

        $update['updated_at'] = now();

        DB::table('team_tasks')
            ->where('id', $taskId)
            ->where('tenant_id', $tenantId)
            ->update($update);

        return true;
    }

    /**
     * Delete a task.
     */
    public function delete(int $taskId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $deleted = DB::table('team_tasks')
            ->where('id', $taskId)
            ->where('tenant_id', $tenantId)
            ->delete();

        if (!$deleted) {
            $this->errors[] = [
                'code' => 'RESOURCE_NOT_FOUND',
                'message' => __('svc_notifications_2.team_task.task_not_found'),
            ];
            return false;
        }

        return true;
    }

    /**
     * Get task statistics for a group.
     */
    public function getStats(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        $counts = DB::table('team_tasks')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status != 'done' THEN 1 ELSE 0 END) as overdue
            ")
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'todo' => (int) ($counts->todo ?? 0),
            'in_progress' => (int) ($counts->in_progress ?? 0),
            'done' => (int) ($counts->done ?? 0),
            'overdue' => (int) ($counts->overdue ?? 0),
        ];
    }
}
