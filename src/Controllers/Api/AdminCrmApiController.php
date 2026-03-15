<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * AdminCrmApiController - V2 API for CRM features in React admin
 *
 * Provides member notes, coordinator tasks, member tags, and onboarding funnel data.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/crm/dashboard        - CRM dashboard stats
 * - GET    /api/v2/admin/crm/funnel            - Onboarding funnel data
 * - GET    /api/v2/admin/crm/notes             - List notes (optional ?user_id=)
 * - POST   /api/v2/admin/crm/notes             - Create note
 * - PUT    /api/v2/admin/crm/notes/{id}        - Update note
 * - DELETE /api/v2/admin/crm/notes/{id}        - Delete note
 * - GET    /api/v2/admin/crm/tasks             - List coordinator tasks
 * - POST   /api/v2/admin/crm/tasks             - Create task
 * - PUT    /api/v2/admin/crm/tasks/{id}        - Update task
 * - DELETE /api/v2/admin/crm/tasks/{id}        - Delete task
 * - GET    /api/v2/admin/crm/tags              - List all tags
 * - POST   /api/v2/admin/crm/tags              - Add tag to member
 * - DELETE /api/v2/admin/crm/tags/{id}         - Remove tag
 * - DELETE /api/v2/admin/crm/tags/bulk?tag=   - Bulk remove tag
 * - GET    /api/v2/admin/crm/timeline          - Member activity timeline
 * - GET    /api/v2/admin/crm/export/notes      - CSV export of notes
 * - GET    /api/v2/admin/crm/export/tasks      - CSV export of tasks
 * - GET    /api/v2/admin/crm/export/dashboard  - CSV export of dashboard stats
 */
class AdminCrmApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/crm/dashboard
     */
    public function dashboard(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Total members
        $totalMembers = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?",
            [$tenantId]
        )->fetch()['cnt'];

        // Active members (logged in within 30 days)
        $activeMembers = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$tenantId]
        )->fetch()['cnt'];

        // New members this month
        $newThisMonth = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            [$tenantId]
        )->fetch()['cnt'];

        // Pending approvals
        $pendingApprovals = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND is_approved = 0",
            [$tenantId]
        )->fetch()['cnt'];

        // Open coordinator tasks
        $openTasks = 0;
        $overdueTasks = 0;
        try {
            $openTasks = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM coordinator_tasks WHERE tenant_id = ? AND status IN ('pending','in_progress')",
                [$tenantId]
            )->fetch()['cnt'];

            $overdueTasks = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM coordinator_tasks WHERE tenant_id = ? AND status IN ('pending','in_progress') AND due_date < CURDATE()",
                [$tenantId]
            )->fetch()['cnt'];
        } catch (\Throwable $e) {
            // Table may not exist yet
        }

        // Total notes
        $totalNotes = 0;
        try {
            $totalNotes = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM member_notes WHERE tenant_id = ?",
                [$tenantId]
            )->fetch()['cnt'];
        } catch (\Throwable $e) {
            // Table may not exist yet
        }

        // Members never logged in
        $neverLoggedIn = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND last_login_at IS NULL AND is_approved = 1",
            [$tenantId]
        )->fetch()['cnt'];

        // Retention rate (active in last 30d / total approved)
        $approvedMembers = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND is_approved = 1",
            [$tenantId]
        )->fetch()['cnt'];
        $retentionRate = $approvedMembers > 0 ? round(($activeMembers / $approvedMembers) * 100, 1) : 0;

        $this->respondWithData([
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'new_this_month' => $newThisMonth,
            'pending_approvals' => $pendingApprovals,
            'open_tasks' => $openTasks,
            'overdue_tasks' => $overdueTasks,
            'total_notes' => $totalNotes,
            'never_logged_in' => $neverLoggedIn,
            'retention_rate' => $retentionRate,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Onboarding Funnel
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/crm/funnel
     */
    public function funnel(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Stage 1: Registered
        $registered = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?",
            [$tenantId]
        )->fetch()['cnt'];

        // Stage 2: Email verified
        $emailVerified = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND email_verified_at IS NOT NULL",
            [$tenantId]
        )->fetch()['cnt'];

        // Stage 3: Profile completed (has bio or location)
        $profileCompleted = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND (bio IS NOT NULL AND bio != '') AND (location IS NOT NULL AND location != '')",
            [$tenantId]
        )->fetch()['cnt'];

        // Stage 4: First listing created
        $firstListing = 0;
        try {
            $firstListing = (int) Database::query(
                "SELECT COUNT(DISTINCT user_id) as cnt FROM listings WHERE tenant_id = ?",
                [$tenantId]
            )->fetch()['cnt'];
        } catch (\Throwable $e) {
            // listings table may not exist
        }

        // Stage 5: First exchange completed (unique users who participated)
        $firstExchange = 0;
        try {
            $firstExchange = (int) Database::query(
                "SELECT COUNT(DISTINCT u) as cnt FROM (
                    SELECT sender_id as u FROM transactions WHERE tenant_id = ? AND status = 'completed'
                    UNION
                    SELECT receiver_id as u FROM transactions WHERE tenant_id = ? AND status = 'completed'
                ) AS combined",
                [$tenantId, $tenantId]
            )->fetch()['cnt'];
        } catch (\Throwable $e) {
            // transactions table may not exist
        }

        // Stage 6: Repeat user (2+ transactions)
        $repeatUser = 0;
        try {
            $repeatUser = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM (
                    SELECT u, COUNT(*) as tx_count FROM (
                        SELECT sender_id as u FROM transactions WHERE tenant_id = ? AND status = 'completed'
                        UNION ALL
                        SELECT receiver_id as u FROM transactions WHERE tenant_id = ? AND status = 'completed'
                    ) AS all_tx GROUP BY u HAVING tx_count >= 2
                ) AS repeat_users",
                [$tenantId, $tenantId]
            )->fetch()['cnt'];
        } catch (\Throwable $e) {
            // transactions table may not exist
        }

        // Monthly registrations for trend chart (last 6 months)
        $monthlyRegistrations = Database::query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
             FROM users WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC",
            [$tenantId]
        )->fetchAll();

        $this->respondWithData([
            'stages' => [
                ['name' => 'Registered', 'count' => $registered, 'color' => '#3b82f6'],
                ['name' => 'Email Verified', 'count' => $emailVerified, 'color' => '#6366f1'],
                ['name' => 'Profile Complete', 'count' => $profileCompleted, 'color' => '#8b5cf6'],
                ['name' => 'First Listing', 'count' => $firstListing, 'color' => '#a855f7'],
                ['name' => 'First Exchange', 'count' => $firstExchange, 'color' => '#d946ef'],
                ['name' => 'Repeat User', 'count' => $repeatUser, 'color' => '#ec4899'],
            ],
            'monthly_registrations' => $monthlyRegistrations,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Member Notes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/crm/notes?user_id=&page=&limit=&category=
     */
    public function listNotes(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
        $category = isset($_GET['category']) ? trim($_GET['category']) : null;
        $search = isset($_GET['search']) ? trim($_GET['search']) : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = "mn.tenant_id = ?";
        $params = [$tenantId];

        if ($userId) {
            $where .= " AND mn.user_id = ?";
            $params[] = $userId;
        }

        $validCategories = ['general', 'outreach', 'support', 'onboarding', 'concern', 'follow_up'];
        if ($category && in_array($category, $validCategories, true)) {
            $where .= " AND mn.category = ?";
            $params[] = $category;
        }

        if ($search && mb_strlen($search) >= 2) {
            $searchTerm = '%' . $search . '%';
            $where .= " AND (mn.content LIKE ? OR u.name LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM member_notes mn
             LEFT JOIN users u ON u.id = mn.user_id
             WHERE {$where}",
            $params
        )->fetch()['cnt'];

        $params[] = $limit;
        $params[] = $offset;

        $notes = Database::query(
            "SELECT mn.*, u.name as user_name, u.avatar_url as user_avatar,
                    a.name as author_name
             FROM member_notes mn
             LEFT JOIN users u ON u.id = mn.user_id
             LEFT JOIN users a ON a.id = mn.author_id
             WHERE {$where}
             ORDER BY mn.is_pinned DESC, mn.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        )->fetchAll();

        $this->respondWithPaginatedCollection($notes, $total, $page, $limit);
    }

    /**
     * POST /api/v2/admin/crm/notes
     */
    public function createNote(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthUserId();
        $input = $this->getJsonInput();

        $userId = (int) ($input['user_id'] ?? 0);
        $content = trim($input['content'] ?? '');
        $category = $input['category'] ?? 'general';

        if (!$userId || !$content) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'user_id and content are required', null, 400);
            return;
        }

        // Verify user belongs to this tenant
        $user = Database::query(
            "SELECT id FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        $validCategories = ['general', 'outreach', 'support', 'onboarding', 'concern', 'follow_up'];
        if (!in_array($category, $validCategories, true)) {
            $category = 'general';
        }

        Database::query(
            "INSERT INTO member_notes (tenant_id, user_id, author_id, content, category, is_pinned)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$tenantId, $userId, $adminId, $content, $category, (int) ($input['is_pinned'] ?? 0)]
        );

        $noteId = Database::lastInsertId();

        $note = Database::query(
            "SELECT mn.*, u.name as user_name, u.avatar_url as user_avatar,
                    a.name as author_name
             FROM member_notes mn
             LEFT JOIN users u ON u.id = mn.user_id
             LEFT JOIN users a ON a.id = mn.author_id
             WHERE mn.id = ? AND mn.tenant_id = ?",
            [$noteId, $tenantId]
        )->fetch();

        $this->respondWithData($note, null, 201);
    }

    /**
     * PUT /api/v2/admin/crm/notes/{id}
     */
    public function updateNote(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getJsonInput();

        $note = Database::query(
            "SELECT id FROM member_notes WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$note) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Note not found', null, 404);
            return;
        }

        $updates = [];
        $params = [];

        if (isset($input['content'])) {
            $updates[] = "content = ?";
            $params[] = trim($input['content']);
        }
        if (isset($input['category'])) {
            $validCategories = ['general', 'outreach', 'support', 'onboarding', 'concern', 'follow_up'];
            if (in_array($input['category'], $validCategories, true)) {
                $updates[] = "category = ?";
                $params[] = $input['category'];
            }
        }
        if (isset($input['is_pinned'])) {
            $updates[] = "is_pinned = ?";
            $params[] = (int) $input['is_pinned'];
        }

        if (empty($updates)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No fields to update', null, 400);
            return;
        }

        $params[] = $id;
        $params[] = $tenantId;

        Database::query(
            "UPDATE member_notes SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        $updated = Database::query(
            "SELECT mn.*, u.name as user_name, u.avatar_url as user_avatar,
                    a.name as author_name
             FROM member_notes mn
             LEFT JOIN users u ON u.id = mn.user_id
             LEFT JOIN users a ON a.id = mn.author_id
             WHERE mn.id = ? AND mn.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        $this->respondWithData($updated);
    }

    /**
     * DELETE /api/v2/admin/crm/notes/{id}
     */
    public function deleteNote(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $note = Database::query(
            "SELECT id FROM member_notes WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$note) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Note not found', null, 404);
            return;
        }

        Database::query(
            "DELETE FROM member_notes WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        $this->respondWithData(['deleted' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Coordinator Tasks
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/crm/tasks?status=&priority=&assigned_to=&page=&limit=
     */
    public function listTasks(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $status = isset($_GET['status']) ? trim($_GET['status']) : null;
        $priority = isset($_GET['priority']) ? trim($_GET['priority']) : null;
        $assignedTo = isset($_GET['assigned_to']) ? (int) $_GET['assigned_to'] : null;
        $search = isset($_GET['search']) ? trim($_GET['search']) : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = "ct.tenant_id = ?";
        $params = [$tenantId];

        $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        if ($status && in_array($status, $validStatuses, true)) {
            $where .= " AND ct.status = ?";
            $params[] = $status;
        }

        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        if ($priority && in_array($priority, $validPriorities, true)) {
            $where .= " AND ct.priority = ?";
            $params[] = $priority;
        }

        if ($assignedTo) {
            $where .= " AND ct.assigned_to = ?";
            $params[] = $assignedTo;
        }

        if ($search && mb_strlen($search) >= 2) {
            $searchTerm = '%' . $search . '%';
            $where .= " AND (ct.title LIKE ? OR ct.description LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM coordinator_tasks ct WHERE {$where}",
            $params
        )->fetch()['cnt'];

        $params[] = $limit;
        $params[] = $offset;

        $tasks = Database::query(
            "SELECT ct.*,
                    assigned.name as assigned_to_name,
                    creator.name as created_by_name,
                    member.name as user_name, member.avatar_url as user_avatar
             FROM coordinator_tasks ct
             LEFT JOIN users assigned ON assigned.id = ct.assigned_to
             LEFT JOIN users creator ON creator.id = ct.created_by
             LEFT JOIN users member ON member.id = ct.user_id
             WHERE {$where}
             ORDER BY
                CASE ct.status WHEN 'pending' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'completed' THEN 2 WHEN 'cancelled' THEN 3 END,
                CASE ct.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
                ct.due_date ASC,
                ct.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        )->fetchAll();

        $this->respondWithPaginatedCollection($tasks, $total, $page, $limit);
    }

    /**
     * POST /api/v2/admin/crm/tasks
     */
    public function createTask(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthUserId();
        $input = $this->getJsonInput();

        $title = trim($input['title'] ?? '');
        $assignedTo = (int) ($input['assigned_to'] ?? $adminId);

        if (!$title) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'title is required', null, 400);
            return;
        }

        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        $priority = in_array($input['priority'] ?? '', $validPriorities, true) ? $input['priority'] : 'medium';

        $userId = isset($input['user_id']) ? (int) $input['user_id'] : null;
        $dueDate = isset($input['due_date']) ? trim($input['due_date']) : null;
        $description = isset($input['description']) ? trim($input['description']) : null;

        // Validate due_date format
        if ($dueDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $dueDate = null;
        }

        Database::query(
            "INSERT INTO coordinator_tasks (tenant_id, assigned_to, user_id, title, description, priority, status, due_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)",
            [$tenantId, $assignedTo, $userId, $title, $description, $priority, $dueDate, $adminId]
        );

        $taskId = Database::lastInsertId();

        $task = Database::query(
            "SELECT ct.*,
                    assigned.name as assigned_to_name,
                    creator.name as created_by_name,
                    member.name as user_name, member.avatar_url as user_avatar
             FROM coordinator_tasks ct
             LEFT JOIN users assigned ON assigned.id = ct.assigned_to
             LEFT JOIN users creator ON creator.id = ct.created_by
             LEFT JOIN users member ON member.id = ct.user_id
             WHERE ct.id = ? AND ct.tenant_id = ?",
            [$taskId, $tenantId]
        )->fetch();

        $this->respondWithData($task, null, 201);
    }

    /**
     * PUT /api/v2/admin/crm/tasks/{id}
     */
    public function updateTask(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getJsonInput();

        $task = Database::query(
            "SELECT id, status FROM coordinator_tasks WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$task) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Task not found', null, 404);
            return;
        }

        $updates = [];
        $params = [];

        if (isset($input['title'])) {
            $updates[] = "title = ?";
            $params[] = trim($input['title']);
        }
        if (isset($input['description'])) {
            $updates[] = "description = ?";
            $params[] = trim($input['description']);
        }
        if (isset($input['priority'])) {
            $validPriorities = ['low', 'medium', 'high', 'urgent'];
            if (in_array($input['priority'], $validPriorities, true)) {
                $updates[] = "priority = ?";
                $params[] = $input['priority'];
            }
        }
        if (isset($input['status'])) {
            $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
            if (in_array($input['status'], $validStatuses, true)) {
                $updates[] = "status = ?";
                $params[] = $input['status'];
                if ($input['status'] === 'completed') {
                    $updates[] = "completed_at = NOW()";
                }
            }
        }
        if (isset($input['assigned_to'])) {
            $updates[] = "assigned_to = ?";
            $params[] = (int) $input['assigned_to'];
        }
        if (isset($input['due_date'])) {
            if ($input['due_date'] === null || $input['due_date'] === '') {
                $updates[] = "due_date = NULL";
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['due_date'])) {
                $updates[] = "due_date = ?";
                $params[] = $input['due_date'];
            }
        }
        if (isset($input['user_id'])) {
            $updates[] = "user_id = ?";
            $params[] = $input['user_id'] ? (int) $input['user_id'] : null;
        }

        if (empty($updates)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No fields to update', null, 400);
            return;
        }

        $params[] = $id;
        $params[] = $tenantId;

        Database::query(
            "UPDATE coordinator_tasks SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        $updated = Database::query(
            "SELECT ct.*,
                    assigned.name as assigned_to_name,
                    creator.name as created_by_name,
                    member.name as user_name, member.avatar_url as user_avatar
             FROM coordinator_tasks ct
             LEFT JOIN users assigned ON assigned.id = ct.assigned_to
             LEFT JOIN users creator ON creator.id = ct.created_by
             LEFT JOIN users member ON member.id = ct.user_id
             WHERE ct.id = ? AND ct.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        $this->respondWithData($updated);
    }

    /**
     * DELETE /api/v2/admin/crm/tasks/{id}
     */
    public function deleteTask(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $task = Database::query(
            "SELECT id FROM coordinator_tasks WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$task) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Task not found', null, 404);
            return;
        }

        Database::query(
            "DELETE FROM coordinator_tasks WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        $this->respondWithData(['deleted' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Member Tags
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/crm/tags?user_id=&tag=
     */
    public function listTags(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
        $tagFilter = isset($_GET['tag']) ? trim($_GET['tag']) : null;

        if ($userId) {
            $tags = Database::query(
                "SELECT mt.*, u.name as user_name
                 FROM member_tags mt
                 LEFT JOIN users u ON u.id = mt.user_id
                 WHERE mt.tenant_id = ? AND mt.user_id = ?
                 ORDER BY mt.tag ASC",
                [$tenantId, $userId]
            )->fetchAll();
        } elseif ($tagFilter) {
            // Return all member tags for a specific tag name
            $tags = Database::query(
                "SELECT mt.*, u.name as user_name, u.avatar_url as user_avatar
                 FROM member_tags mt
                 LEFT JOIN users u ON u.id = mt.user_id
                 WHERE mt.tenant_id = ? AND mt.tag = ?
                 ORDER BY mt.created_at DESC",
                [$tenantId, $tagFilter]
            )->fetchAll();
        } else {
            // Return tag summary (unique tags with counts)
            $tags = Database::query(
                "SELECT tag, COUNT(*) as member_count
                 FROM member_tags
                 WHERE tenant_id = ?
                 GROUP BY tag
                 ORDER BY member_count DESC",
                [$tenantId]
            )->fetchAll();
        }

        $this->respondWithCollection($tags);
    }

    /**
     * POST /api/v2/admin/crm/tags
     */
    public function addTag(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthUserId();
        $input = $this->getJsonInput();

        $userId = (int) ($input['user_id'] ?? 0);
        $tag = trim($input['tag'] ?? '');

        if (!$userId || !$tag) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'user_id and tag are required', null, 400);
            return;
        }

        if (mb_strlen($tag) > 50) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_TOO_LONG, 'Tag must be 50 characters or less', null, 400);
            return;
        }

        // Check user exists in tenant
        $user = Database::query(
            "SELECT id FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        try {
            Database::query(
                "INSERT INTO member_tags (tenant_id, user_id, tag, created_by)
                 VALUES (?, ?, ?, ?)",
                [$tenantId, $userId, $tag, $adminId]
            );
        } catch (\Throwable $e) {
            // Duplicate tag — that's fine
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_ALREADY_EXISTS, 'Tag already assigned', null, 409);
                return;
            }
            throw $e;
        }

        $tagId = Database::lastInsertId();

        $this->respondWithData([
            'id' => $tagId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'tag' => $tag,
            'created_by' => $adminId,
        ], null, 201);
    }

    /**
     * DELETE /api/v2/admin/crm/tags/bulk?tag=
     * Remove all instances of a tag across all members.
     */
    public function bulkRemoveTag(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $tag = isset($_GET['tag']) ? trim($_GET['tag']) : '';
        if (!$tag) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'tag parameter is required', null, 400);
            return;
        }

        $count = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM member_tags WHERE tenant_id = ? AND tag = ?",
            [$tenantId, $tag]
        )->fetch()['cnt'];

        if ($count === 0) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Tag not found', null, 404);
            return;
        }

        Database::query(
            "DELETE FROM member_tags WHERE tenant_id = ? AND tag = ?",
            [$tenantId, $tag]
        );

        $this->respondWithData(['deleted' => $count]);
    }

    /**
     * DELETE /api/v2/admin/crm/tags/{id}
     */
    public function removeTag(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $tag = Database::query(
            "SELECT id FROM member_tags WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$tag) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Tag not found', null, 404);
            return;
        }

        Database::query(
            "DELETE FROM member_tags WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        $this->respondWithData(['deleted' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin list (for task assignment dropdowns)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/crm/admins
     */
    public function listAdmins(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $admins = Database::query(
            "SELECT id, name, email, avatar_url, role
             FROM users
             WHERE tenant_id = ? AND (role IN ('admin','moderator','tenant_admin','super_admin') OR is_admin = 1)
             ORDER BY name ASC",
            [$tenantId]
        )->fetchAll();

        $this->respondWithCollection($admins);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Activity Timeline
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/crm/timeline?user_id=&type=&days=&page=&limit=
     */
    public function timeline(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
        $type = isset($_GET['type']) ? trim($_GET['type']) : null;
        $days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        // Build timeline from multiple sources using UNION ALL
        $unions = [];
        $params = [];
        $countParams = [];

        // 1. Logins (from users.last_login_at — we use activity_log if available, else users)
        if (!$type || $type === 'login') {
            $unions[] = "SELECT 'login' as activity_type, u.id as user_id, u.name as user_name, u.avatar_url as user_avatar,
                         'Logged in' as description, NULL as metadata, u.last_login_at as created_at
                         FROM users u WHERE u.tenant_id = ? AND u.last_login_at IS NOT NULL"
                         . ($userId ? " AND u.id = ?" : "")
                         . ($days > 0 ? " AND u.last_login_at >= DATE_SUB(NOW(), INTERVAL ? DAY)" : "");
            $params[] = $tenantId;
            $countParams[] = $tenantId;
            if ($userId) { $params[] = $userId; $countParams[] = $userId; }
            if ($days > 0) { $params[] = $days; $countParams[] = $days; }
        }

        // 2. Signups
        if (!$type || $type === 'signup') {
            $unions[] = "SELECT 'signup' as activity_type, u.id as user_id, u.name as user_name, u.avatar_url as user_avatar,
                         'Registered an account' as description, NULL as metadata, u.created_at as created_at
                         FROM users u WHERE u.tenant_id = ?"
                         . ($userId ? " AND u.id = ?" : "")
                         . ($days > 0 ? " AND u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)" : "");
            $params[] = $tenantId;
            $countParams[] = $tenantId;
            if ($userId) { $params[] = $userId; $countParams[] = $userId; }
            if ($days > 0) { $params[] = $days; $countParams[] = $days; }
        }

        // 3. Listings created
        if (!$type || $type === 'listing_created') {
            try {
                Database::query("SELECT 1 FROM listings LIMIT 1");
                $unions[] = "SELECT 'listing_created' as activity_type, l.user_id, u.name as user_name, u.avatar_url as user_avatar,
                             CONCAT('Created listing: ', l.title) as description, NULL as metadata, l.created_at
                             FROM listings l LEFT JOIN users u ON u.id = l.user_id
                             WHERE l.tenant_id = ?"
                             . ($userId ? " AND l.user_id = ?" : "")
                             . ($days > 0 ? " AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)" : "");
                $params[] = $tenantId;
                $countParams[] = $tenantId;
                if ($userId) { $params[] = $userId; $countParams[] = $userId; }
                if ($days > 0) { $params[] = $days; $countParams[] = $days; }
            } catch (\Throwable $e) {
                // Table doesn't exist
            }
        }

        // 4. Exchanges completed
        if (!$type || $type === 'exchange_completed') {
            try {
                Database::query("SELECT 1 FROM transactions LIMIT 1");
                $unions[] = "SELECT 'exchange_completed' as activity_type, t.sender_id as user_id, u.name as user_name, u.avatar_url as user_avatar,
                             CONCAT('Completed exchange with ', r.name) as description, NULL as metadata, t.created_at
                             FROM transactions t
                             LEFT JOIN users u ON u.id = t.sender_id
                             LEFT JOIN users r ON r.id = t.receiver_id
                             WHERE t.tenant_id = ? AND t.status = 'completed'"
                             . ($userId ? " AND t.sender_id = ?" : "")
                             . ($days > 0 ? " AND t.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)" : "");
                $params[] = $tenantId;
                $countParams[] = $tenantId;
                if ($userId) { $params[] = $userId; $countParams[] = $userId; }
                if ($days > 0) { $params[] = $days; $countParams[] = $days; }
            } catch (\Throwable $e) {
                // Table doesn't exist
            }
        }

        // 5. Notes added
        if (!$type || $type === 'note_added') {
            try {
                Database::query("SELECT 1 FROM member_notes LIMIT 1");
                $unions[] = "SELECT 'note_added' as activity_type, mn.user_id, u.name as user_name, u.avatar_url as user_avatar,
                             CONCAT('Note added by ', a.name, ': ', LEFT(mn.content, 80)) as description, NULL as metadata, mn.created_at
                             FROM member_notes mn
                             LEFT JOIN users u ON u.id = mn.user_id
                             LEFT JOIN users a ON a.id = mn.author_id
                             WHERE mn.tenant_id = ?"
                             . ($userId ? " AND mn.user_id = ?" : "")
                             . ($days > 0 ? " AND mn.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)" : "");
                $params[] = $tenantId;
                $countParams[] = $tenantId;
                if ($userId) { $params[] = $userId; $countParams[] = $userId; }
                if ($days > 0) { $params[] = $days; $countParams[] = $days; }
            } catch (\Throwable $e) {
                // Table doesn't exist
            }
        }

        // 6. Coordinator tasks created
        if (!$type || $type === 'task_created') {
            try {
                Database::query("SELECT 1 FROM coordinator_tasks LIMIT 1");
                $unions[] = "SELECT 'task_created' as activity_type, ct.created_by as user_id, u.name as user_name, u.avatar_url as user_avatar,
                             CONCAT('Created task: ', ct.title) as description, NULL as metadata, ct.created_at
                             FROM coordinator_tasks ct LEFT JOIN users u ON u.id = ct.created_by
                             WHERE ct.tenant_id = ?"
                             . ($userId ? " AND ct.created_by = ?" : "")
                             . ($days > 0 ? " AND ct.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)" : "");
                $params[] = $tenantId;
                $countParams[] = $tenantId;
                if ($userId) { $params[] = $userId; $countParams[] = $userId; }
                if ($days > 0) { $params[] = $days; $countParams[] = $days; }
            } catch (\Throwable $e) {
                // Table doesn't exist
            }
        }

        // 7. Group joins
        if (!$type || $type === 'group_joined') {
            try {
                Database::query("SELECT 1 FROM group_members LIMIT 1");
                $unions[] = "SELECT 'group_joined' as activity_type, gm.user_id, u.name as user_name, u.avatar_url as user_avatar,
                             CONCAT('Joined group: ', g.name) as description, NULL as metadata, gm.created_at
                             FROM group_members gm
                             LEFT JOIN users u ON u.id = gm.user_id
                             INNER JOIN `groups` g ON g.id = gm.group_id AND g.tenant_id = ?
                             WHERE 1=1"
                             . ($userId ? " AND gm.user_id = ?" : "")
                             . ($days > 0 ? " AND gm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)" : "");
                $params[] = $tenantId;
                $countParams[] = $tenantId;
                if ($userId) { $params[] = $userId; $countParams[] = $userId; }
                if ($days > 0) { $params[] = $days; $countParams[] = $days; }
            } catch (\Throwable $e) {
                // Table doesn't exist
            }
        }

        // 8. Profile updated (users.updated_at != created_at)
        if (!$type || $type === 'profile_updated') {
            $unions[] = "SELECT 'profile_updated' as activity_type, u.id as user_id, u.name as user_name, u.avatar_url as user_avatar,
                         'Updated their profile' as description, NULL as metadata, u.updated_at as created_at
                         FROM users u WHERE u.tenant_id = ? AND u.updated_at > u.created_at"
                         . ($userId ? " AND u.id = ?" : "")
                         . ($days > 0 ? " AND u.updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)" : "");
            $params[] = $tenantId;
            $countParams[] = $tenantId;
            if ($userId) { $params[] = $userId; $countParams[] = $userId; }
            if ($days > 0) { $params[] = $days; $countParams[] = $days; }
        }

        if (empty($unions)) {
            $this->respondWithPaginatedCollection([], 0, $page, $limit);
            return;
        }

        $unionSql = implode(" UNION ALL ", $unions);

        // Count
        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM ({$unionSql}) AS timeline",
            $countParams
        )->fetch()['cnt'];

        // Data
        $params[] = $limit;
        $params[] = $offset;
        $entries = Database::query(
            "SELECT * FROM ({$unionSql}) AS timeline ORDER BY created_at DESC LIMIT ? OFFSET ?",
            $params
        )->fetchAll();

        // Add sequential IDs for frontend keying
        foreach ($entries as $i => &$entry) {
            $entry['id'] = ($page - 1) * $limit + $i + 1;
        }

        $this->respondWithPaginatedCollection($entries, $total, $page, $limit);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CSV Exports
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/crm/export/notes
     */
    public function exportNotes(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $notes = Database::query(
            "SELECT mn.id, mn.user_id, u.name as user_name, mn.content, mn.category,
                    mn.is_pinned, a.name as author_name, mn.created_at, mn.updated_at
             FROM member_notes mn
             LEFT JOIN users u ON u.id = mn.user_id
             LEFT JOIN users a ON a.id = mn.author_id
             WHERE mn.tenant_id = ?
             ORDER BY mn.created_at DESC",
            [$tenantId]
        )->fetchAll();

        $this->sendCsv('crm-notes', ['ID', 'User ID', 'User Name', 'Content', 'Category', 'Pinned', 'Author', 'Created', 'Updated'], $notes);
    }

    /**
     * GET /api/v2/admin/crm/export/tasks
     */
    public function exportTasks(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $tasks = Database::query(
            "SELECT ct.id, ct.title, ct.description, ct.priority, ct.status,
                    assigned.name as assigned_to_name, member.name as related_member,
                    ct.due_date, ct.completed_at, creator.name as created_by_name, ct.created_at
             FROM coordinator_tasks ct
             LEFT JOIN users assigned ON assigned.id = ct.assigned_to
             LEFT JOIN users creator ON creator.id = ct.created_by
             LEFT JOIN users member ON member.id = ct.user_id
             WHERE ct.tenant_id = ?
             ORDER BY ct.created_at DESC",
            [$tenantId]
        )->fetchAll();

        $this->sendCsv('crm-tasks', ['ID', 'Title', 'Description', 'Priority', 'Status', 'Assigned To', 'Related Member', 'Due Date', 'Completed At', 'Created By', 'Created'], $tasks);
    }

    /**
     * GET /api/v2/admin/crm/export/dashboard
     */
    public function exportDashboard(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $totalMembers = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?", [$tenantId])->fetch()['cnt'];
        $activeMembers = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$tenantId])->fetch()['cnt'];
        $newThisMonth = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [$tenantId])->fetch()['cnt'];
        $pendingApprovals = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND is_approved = 0", [$tenantId])->fetch()['cnt'];
        $approvedMembers = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND is_approved = 1", [$tenantId])->fetch()['cnt'];
        $retentionRate = $approvedMembers > 0 ? round(($activeMembers / $approvedMembers) * 100, 1) : 0;

        $rows = [
            ['metric' => 'Total Members', 'value' => $totalMembers],
            ['metric' => 'Active Members', 'value' => $activeMembers],
            ['metric' => 'New This Month', 'value' => $newThisMonth],
            ['metric' => 'Pending Approvals', 'value' => $pendingApprovals],
            ['metric' => 'Retention Rate', 'value' => $retentionRate . '%'],
        ];

        $this->sendCsv('crm-dashboard', ['Metric', 'Value'], $rows);
    }

    /**
     * Send CSV response
     */
    private function sendCsv(string $filename, array $headers, array $rows): void
    {
        $date = date('Y-m-d');
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}-{$date}.csv\"");
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);

        foreach ($rows as $row) {
            fputcsv($output, array_values($row));
        }

        fclose($output);
        exit;
    }
}
