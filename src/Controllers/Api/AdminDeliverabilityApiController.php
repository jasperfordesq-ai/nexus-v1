<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * AdminDeliverabilityApiController - V2 API for React admin deliverability tracking
 *
 * Provides CRUD for deliverables, milestones, comments, and analytics.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/deliverability/dashboard       - Dashboard stats
 * - GET    /api/v2/admin/deliverability/analytics        - Analytics & trends
 * - GET    /api/v2/admin/deliverability                  - List deliverables (paginated, filterable)
 * - POST   /api/v2/admin/deliverability                  - Create a new deliverable
 * - GET    /api/v2/admin/deliverability/{id}             - Get single deliverable with milestones & comments
 * - PUT    /api/v2/admin/deliverability/{id}             - Update a deliverable
 * - DELETE /api/v2/admin/deliverability/{id}             - Delete a deliverable
 * - POST   /api/v2/admin/deliverability/{id}/comments    - Add comment to deliverable
 */
class AdminDeliverabilityApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * Valid status values for deliverables
     */
    private const VALID_STATUSES = [
        'draft', 'ready', 'in_progress', 'blocked', 'review',
        'completed', 'cancelled', 'on_hold',
    ];

    /**
     * Valid priority values for deliverables
     */
    private const VALID_PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Extract the deliverable ID from the request URI
     */
    private function extractDeliverableId(): int
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/deliverability/(\d+)#', $uri, $matches);
        return (int) ($matches[1] ?? 0);
    }

    /**
     * Get the authenticated user ID from the Bearer token
     */
    private function getAuthUserId(): ?int
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/', $authHeader, $m)) {
            try {
                $payload = \Nexus\Services\TokenService::validateAccessToken($m[1]);
                return (int) ($payload['user_id'] ?? $payload['sub'] ?? 0) ?: null;
            } catch (\Exception $e) {
            }
        }
        return null;
    }

    /**
     * Log a history entry for a deliverable change
     */
    private function logHistory(
        int $tenantId,
        int $deliverableId,
        string $actionType,
        ?int $userId,
        ?string $fieldName = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $description = null
    ): void {
        Database::query(
            "INSERT INTO deliverable_history
                (tenant_id, deliverable_id, action_type, user_id, action_timestamp,
                 field_name, old_value, new_value, change_description, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, NOW())",
            [
                $tenantId,
                $deliverableId,
                $actionType,
                $userId,
                $fieldName,
                $oldValue,
                $newValue,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]
        );
    }

    // ============================================
    // DASHBOARD & ANALYTICS
    // ============================================

    /**
     * GET /api/v2/admin/deliverability/dashboard
     *
     * Returns summary stats: total count, counts by status, overdue count,
     * completion rate, and recent activity.
     */
    public function getDashboard(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Total deliverables
        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM deliverables WHERE tenant_id = ?",
            [$tenantId]
        )->fetch()['cnt'];

        // Counts by status
        $statusRows = Database::query(
            "SELECT status, COUNT(*) as cnt FROM deliverables WHERE tenant_id = ? GROUP BY status",
            [$tenantId]
        )->fetchAll();

        $byStatus = [];
        foreach ($statusRows as $row) {
            $byStatus[$row['status']] = (int) $row['cnt'];
        }

        // Overdue count (due_date in the past, not completed/cancelled)
        $overdue = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM deliverables
             WHERE tenant_id = ? AND due_date < CURDATE()
               AND status NOT IN ('completed', 'cancelled')",
            [$tenantId]
        )->fetch()['cnt'];

        // Completion rate
        $completed = (int) ($byStatus['completed'] ?? 0);
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        // Recent activity (last 10 history entries)
        $recentActivity = Database::query(
            "SELECT h.id, h.deliverable_id, h.action_type, h.field_name,
                    h.change_description, h.action_timestamp,
                    d.title as deliverable_title,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name
             FROM deliverable_history h
             LEFT JOIN deliverables d ON h.deliverable_id = d.id
             LEFT JOIN users u ON h.user_id = u.id
             WHERE h.tenant_id = ?
             ORDER BY h.action_timestamp DESC
             LIMIT 10",
            [$tenantId]
        )->fetchAll();

        $formattedActivity = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'deliverable_id' => (int) $row['deliverable_id'],
                'deliverable_title' => $row['deliverable_title'] ?? '',
                'action_type' => $row['action_type'],
                'field_name' => $row['field_name'],
                'change_description' => $row['change_description'],
                'user_name' => trim($row['user_name'] ?? ''),
                'action_timestamp' => $row['action_timestamp'],
            ];
        }, $recentActivity);

        $this->respondWithData([
            'total' => $total,
            'by_status' => $byStatus,
            'overdue' => $overdue,
            'completion_rate' => $completionRate,
            'recent_activity' => $formattedActivity,
        ]);
    }

    /**
     * GET /api/v2/admin/deliverability/analytics
     *
     * Returns analytics: completion trends (last 30 days), priority distribution,
     * average time to complete, risk distribution.
     */
    public function getAnalytics(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Completion trends: deliverables completed per day over last 30 days
        $completionTrends = Database::query(
            "SELECT DATE(completed_at) as date, COUNT(*) as count
             FROM deliverables
             WHERE tenant_id = ? AND completed_at IS NOT NULL
               AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(completed_at)
             ORDER BY date ASC",
            [$tenantId]
        )->fetchAll();

        $formattedTrends = array_map(function ($row) {
            return [
                'date' => $row['date'],
                'count' => (int) $row['count'],
            ];
        }, $completionTrends);

        // Priority distribution
        $priorityRows = Database::query(
            "SELECT priority, COUNT(*) as cnt FROM deliverables
             WHERE tenant_id = ?
             GROUP BY priority",
            [$tenantId]
        )->fetchAll();

        $priorityDistribution = [];
        foreach ($priorityRows as $row) {
            $priorityDistribution[$row['priority']] = (int) $row['cnt'];
        }

        // Average time to complete (in days) for completed deliverables
        $avgTime = Database::query(
            "SELECT AVG(DATEDIFF(completed_at, created_at)) as avg_days
             FROM deliverables
             WHERE tenant_id = ? AND completed_at IS NOT NULL AND status = 'completed'",
            [$tenantId]
        )->fetch();

        $avgDaysToComplete = $avgTime['avg_days'] !== null
            ? round((float) $avgTime['avg_days'], 1)
            : null;

        // Risk distribution
        $riskRows = Database::query(
            "SELECT risk_level, COUNT(*) as cnt FROM deliverables
             WHERE tenant_id = ? AND risk_level IS NOT NULL
             GROUP BY risk_level",
            [$tenantId]
        )->fetchAll();

        $riskDistribution = [];
        foreach ($riskRows as $row) {
            $riskDistribution[$row['risk_level']] = (int) $row['cnt'];
        }

        $this->respondWithData([
            'completion_trends' => $formattedTrends,
            'priority_distribution' => $priorityDistribution,
            'avg_days_to_complete' => $avgDaysToComplete,
            'risk_distribution' => $riskDistribution,
        ]);
    }

    // ============================================
    // DELIVERABLES CRUD
    // ============================================

    /**
     * GET /api/v2/admin/deliverability
     *
     * Query params: page, limit, status, priority, assigned_to, search
     */
    public function getDeliverables(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? null;
        $priority = $_GET['priority'] ?? null;
        $assignedTo = isset($_GET['assigned_to']) ? (int) $_GET['assigned_to'] : null;
        $search = $_GET['search'] ?? null;

        $conditions = ['d.tenant_id = ?'];
        $params = [$tenantId];

        // Status filter
        if ($status && in_array($status, self::VALID_STATUSES, true)) {
            $conditions[] = 'd.status = ?';
            $params[] = $status;
        }

        // Priority filter
        if ($priority && in_array($priority, self::VALID_PRIORITIES, true)) {
            $conditions[] = 'd.priority = ?';
            $params[] = $priority;
        }

        // Assigned-to filter
        if ($assignedTo) {
            $conditions[] = 'd.assigned_to = ?';
            $params[] = $assignedTo;
        }

        // Search filter
        if ($search) {
            $conditions[] = '(d.title LIKE ? OR d.description LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $where = implode(' AND ', $conditions);

        // Total count
        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM deliverables d WHERE {$where}",
            $params
        )->fetch()['cnt'];

        // Paginated results with owner/assignee names
        $items = Database::query(
            "SELECT d.id, d.title, d.description, d.category, d.priority,
                    d.owner_id, d.assigned_to, d.assigned_group_id,
                    d.start_date, d.due_date, d.completed_at,
                    d.status, d.progress_percentage,
                    d.estimated_hours, d.actual_hours,
                    d.parent_deliverable_id, d.tags,
                    d.delivery_confidence, d.risk_level, d.risk_notes,
                    d.created_at, d.updated_at,
                    CONCAT(COALESCE(owner.first_name, ''), ' ', COALESCE(owner.last_name, '')) as owner_name,
                    CONCAT(COALESCE(assignee.first_name, ''), ' ', COALESCE(assignee.last_name, '')) as assignee_name
             FROM deliverables d
             LEFT JOIN users owner ON d.owner_id = owner.id
             LEFT JOIN users assignee ON d.assigned_to = assignee.id
             WHERE {$where}
             ORDER BY d.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        )->fetchAll();

        // Format for frontend
        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'category' => $row['category'] ?? null,
                'priority' => $row['priority'] ?? 'medium',
                'owner_id' => $row['owner_id'] ? (int) $row['owner_id'] : null,
                'owner_name' => trim($row['owner_name'] ?? ''),
                'assigned_to' => $row['assigned_to'] ? (int) $row['assigned_to'] : null,
                'assignee_name' => trim($row['assignee_name'] ?? ''),
                'assigned_group_id' => $row['assigned_group_id'] ? (int) $row['assigned_group_id'] : null,
                'start_date' => $row['start_date'] ?? null,
                'due_date' => $row['due_date'] ?? null,
                'completed_at' => $row['completed_at'] ?? null,
                'status' => $row['status'] ?? 'draft',
                'progress_percentage' => (int) ($row['progress_percentage'] ?? 0),
                'estimated_hours' => $row['estimated_hours'] !== null ? (float) $row['estimated_hours'] : null,
                'actual_hours' => $row['actual_hours'] !== null ? (float) $row['actual_hours'] : null,
                'parent_deliverable_id' => $row['parent_deliverable_id'] ? (int) $row['parent_deliverable_id'] : null,
                'tags' => json_decode($row['tags'] ?? '[]', true) ?: [],
                'delivery_confidence' => $row['delivery_confidence'] !== null ? (int) $row['delivery_confidence'] : null,
                'risk_level' => $row['risk_level'] ?? null,
                'risk_notes' => $row['risk_notes'] ?? null,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $items);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/deliverability/{id}
     *
     * Returns the deliverable with its milestones and recent comments.
     */
    public function getDeliverable(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $id = $this->extractDeliverableId();
        if (!$id) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Deliverable ID is required', 'id', 400);
            return;
        }

        // Fetch deliverable
        $deliverable = Database::query(
            "SELECT d.*,
                    CONCAT(COALESCE(owner.first_name, ''), ' ', COALESCE(owner.last_name, '')) as owner_name,
                    CONCAT(COALESCE(assignee.first_name, ''), ' ', COALESCE(assignee.last_name, '')) as assignee_name
             FROM deliverables d
             LEFT JOIN users owner ON d.owner_id = owner.id
             LEFT JOIN users assignee ON d.assigned_to = assignee.id
             WHERE d.id = ? AND d.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$deliverable) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Deliverable not found', null, 404);
            return;
        }

        // Fetch milestones
        $milestones = Database::query(
            "SELECT m.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as completed_by_name
             FROM deliverable_milestones m
             LEFT JOIN users u ON m.completed_by = u.id
             WHERE m.deliverable_id = ? AND m.tenant_id = ?
             ORDER BY m.order_position ASC",
            [$id, $tenantId]
        )->fetchAll();

        $formattedMilestones = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'order_position' => (int) ($row['order_position'] ?? 0),
                'status' => $row['status'] ?? 'pending',
                'completed_at' => $row['completed_at'] ?? null,
                'completed_by' => $row['completed_by'] ? (int) $row['completed_by'] : null,
                'completed_by_name' => trim($row['completed_by_name'] ?? ''),
                'due_date' => $row['due_date'] ?? null,
                'estimated_hours' => $row['estimated_hours'] !== null ? (float) $row['estimated_hours'] : null,
                'depends_on_milestone_ids' => json_decode($row['depends_on_milestone_ids'] ?? '[]', true) ?: [],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $milestones);

        // Fetch recent comments (last 20)
        $comments = Database::query(
            "SELECT c.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                    u.avatar_url as user_avatar
             FROM deliverable_comments c
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.deliverable_id = ? AND c.tenant_id = ? AND c.is_deleted = 0
             ORDER BY c.created_at DESC
             LIMIT 20",
            [$id, $tenantId]
        )->fetchAll();

        $formattedComments = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'user_id' => (int) ($row['user_id'] ?? 0),
                'user_name' => trim($row['user_name'] ?? ''),
                'user_avatar' => $row['user_avatar'] ?? null,
                'comment_text' => $row['comment_text'] ?? '',
                'comment_type' => $row['comment_type'] ?? 'comment',
                'parent_comment_id' => $row['parent_comment_id'] ? (int) $row['parent_comment_id'] : null,
                'reactions' => json_decode($row['reactions'] ?? '[]', true) ?: [],
                'is_pinned' => (bool) ($row['is_pinned'] ?? false),
                'is_edited' => (bool) ($row['is_edited'] ?? false),
                'edited_at' => $row['edited_at'] ?? null,
                'mentioned_user_ids' => json_decode($row['mentioned_user_ids'] ?? '[]', true) ?: [],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $comments);

        // Format the deliverable
        $formatted = [
            'id' => (int) $deliverable['id'],
            'title' => $deliverable['title'] ?? '',
            'description' => $deliverable['description'] ?? '',
            'category' => $deliverable['category'] ?? null,
            'priority' => $deliverable['priority'] ?? 'medium',
            'owner_id' => $deliverable['owner_id'] ? (int) $deliverable['owner_id'] : null,
            'owner_name' => trim($deliverable['owner_name'] ?? ''),
            'assigned_to' => $deliverable['assigned_to'] ? (int) $deliverable['assigned_to'] : null,
            'assignee_name' => trim($deliverable['assignee_name'] ?? ''),
            'assigned_group_id' => $deliverable['assigned_group_id'] ? (int) $deliverable['assigned_group_id'] : null,
            'start_date' => $deliverable['start_date'] ?? null,
            'due_date' => $deliverable['due_date'] ?? null,
            'completed_at' => $deliverable['completed_at'] ?? null,
            'status' => $deliverable['status'] ?? 'draft',
            'progress_percentage' => (int) ($deliverable['progress_percentage'] ?? 0),
            'estimated_hours' => $deliverable['estimated_hours'] !== null ? (float) $deliverable['estimated_hours'] : null,
            'actual_hours' => $deliverable['actual_hours'] !== null ? (float) $deliverable['actual_hours'] : null,
            'parent_deliverable_id' => $deliverable['parent_deliverable_id'] ? (int) $deliverable['parent_deliverable_id'] : null,
            'blocking_deliverable_ids' => json_decode($deliverable['blocking_deliverable_ids'] ?? '[]', true) ?: [],
            'depends_on_deliverable_ids' => json_decode($deliverable['depends_on_deliverable_ids'] ?? '[]', true) ?: [],
            'tags' => json_decode($deliverable['tags'] ?? '[]', true) ?: [],
            'custom_fields' => json_decode($deliverable['custom_fields'] ?? '{}', true) ?: [],
            'delivery_confidence' => $deliverable['delivery_confidence'] !== null ? (int) $deliverable['delivery_confidence'] : null,
            'risk_level' => $deliverable['risk_level'] ?? null,
            'risk_notes' => $deliverable['risk_notes'] ?? null,
            'watchers' => json_decode($deliverable['watchers'] ?? '[]', true) ?: [],
            'collaborators' => json_decode($deliverable['collaborators'] ?? '[]', true) ?: [],
            'attachment_urls' => json_decode($deliverable['attachment_urls'] ?? '[]', true) ?: [],
            'external_links' => json_decode($deliverable['external_links'] ?? '[]', true) ?: [],
            'created_at' => $deliverable['created_at'],
            'updated_at' => $deliverable['updated_at'] ?? null,
            'milestones' => $formattedMilestones,
            'comments' => $formattedComments,
        ];

        $this->respondWithData($formatted);
    }

    /**
     * POST /api/v2/admin/deliverability
     *
     * Create a new deliverable. Required: title.
     * Owner is set to the authenticated user.
     */
    public function createDeliverable(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $data = $this->getAllInput();
        $title = trim($data['title'] ?? '');

        if (empty($title)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Title is required',
                'title',
                400
            );
            return;
        }

        $userId = $this->getAuthUserId() ?? $adminId;

        // Validate status and priority if provided
        $status = 'draft';
        if (isset($data['status']) && in_array($data['status'], self::VALID_STATUSES, true)) {
            $status = $data['status'];
        }

        $priority = 'medium';
        if (isset($data['priority']) && in_array($data['priority'], self::VALID_PRIORITIES, true)) {
            $priority = $data['priority'];
        }

        // Prepare JSON fields
        $tags = isset($data['tags']) ? json_encode($data['tags']) : '[]';
        $customFields = isset($data['custom_fields']) ? json_encode($data['custom_fields']) : '{}';
        $blockingIds = isset($data['blocking_deliverable_ids']) ? json_encode($data['blocking_deliverable_ids']) : '[]';
        $dependsOnIds = isset($data['depends_on_deliverable_ids']) ? json_encode($data['depends_on_deliverable_ids']) : '[]';
        $watchers = isset($data['watchers']) ? json_encode($data['watchers']) : '[]';
        $collaborators = isset($data['collaborators']) ? json_encode($data['collaborators']) : '[]';
        $attachmentUrls = isset($data['attachment_urls']) ? json_encode($data['attachment_urls']) : '[]';
        $externalLinks = isset($data['external_links']) ? json_encode($data['external_links']) : '[]';

        Database::query(
            "INSERT INTO deliverables
                (tenant_id, title, description, category, priority, owner_id, assigned_to,
                 assigned_group_id, start_date, due_date, status, progress_percentage,
                 estimated_hours, parent_deliverable_id, blocking_deliverable_ids,
                 depends_on_deliverable_ids, tags, custom_fields, delivery_confidence,
                 risk_level, risk_notes, watchers, collaborators, attachment_urls,
                 external_links, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $tenantId,
                $title,
                $data['description'] ?? null,
                $data['category'] ?? null,
                $priority,
                $userId,
                isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
                isset($data['assigned_group_id']) ? (int) $data['assigned_group_id'] : null,
                $data['start_date'] ?? null,
                $data['due_date'] ?? null,
                $status,
                (int) ($data['progress_percentage'] ?? 0),
                isset($data['estimated_hours']) ? (float) $data['estimated_hours'] : null,
                isset($data['parent_deliverable_id']) ? (int) $data['parent_deliverable_id'] : null,
                $blockingIds,
                $dependsOnIds,
                $tags,
                $customFields,
                isset($data['delivery_confidence']) ? (int) $data['delivery_confidence'] : null,
                $data['risk_level'] ?? null,
                $data['risk_notes'] ?? null,
                $watchers,
                $collaborators,
                $attachmentUrls,
                $externalLinks,
            ]
        );

        $newId = Database::lastInsertId();

        // Log history
        $this->logHistory(
            $tenantId,
            (int) $newId,
            'created',
            $userId,
            null,
            null,
            null,
            "Created deliverable: {$title}"
        );

        $this->respondWithData([
            'id' => (int) $newId,
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
        ], null, 201);
    }

    /**
     * PUT /api/v2/admin/deliverability/{id}
     *
     * Update deliverable fields. Logs history for status, priority, and assignment changes.
     */
    public function updateDeliverable(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $id = $this->extractDeliverableId();
        if (!$id) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Deliverable ID is required', 'id', 400);
            return;
        }

        // Verify deliverable exists and belongs to tenant
        $existing = Database::query(
            "SELECT id, title, status, priority, assigned_to FROM deliverables WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$existing) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Deliverable not found', null, 404);
            return;
        }

        $data = $this->getAllInput();
        $userId = $this->getAuthUserId() ?? $adminId;

        // Build dynamic update
        $fields = [];
        $params = [];

        // Simple text/nullable fields
        $simpleFields = [
            'title', 'description', 'category', 'risk_level', 'risk_notes',
            'start_date', 'due_date',
        ];
        foreach ($simpleFields as $fieldName) {
            if (array_key_exists($fieldName, $data)) {
                $fields[] = "{$fieldName} = ?";
                $value = $data[$fieldName];
                $params[] = is_string($value) ? trim($value) : $value;
            }
        }

        // Validate title is not empty if provided
        if (isset($data['title']) && trim($data['title']) === '') {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Title cannot be empty',
                'title',
                400
            );
            return;
        }

        // Status with validation and history logging
        if (isset($data['status'])) {
            if (!in_array($data['status'], self::VALID_STATUSES, true)) {
                $this->respondWithError('VALIDATION_INVALID_VALUE', 'Invalid status value', 'status', 400);
                return;
            }
            $fields[] = 'status = ?';
            $params[] = $data['status'];

            // Set completed_at when status changes to completed
            if ($data['status'] === 'completed' && $existing['status'] !== 'completed') {
                $fields[] = 'completed_at = NOW()';
            } elseif ($data['status'] !== 'completed' && $existing['status'] === 'completed') {
                $fields[] = 'completed_at = NULL';
            }

            if ($data['status'] !== $existing['status']) {
                $this->logHistory($tenantId, $id, 'status_changed', $userId, 'status', $existing['status'], $data['status'],
                    "Status changed from {$existing['status']} to {$data['status']}");
            }
        }

        // Priority with validation and history logging
        if (isset($data['priority'])) {
            if (!in_array($data['priority'], self::VALID_PRIORITIES, true)) {
                $this->respondWithError('VALIDATION_INVALID_VALUE', 'Invalid priority value', 'priority', 400);
                return;
            }
            $fields[] = 'priority = ?';
            $params[] = $data['priority'];

            if ($data['priority'] !== $existing['priority']) {
                $this->logHistory($tenantId, $id, 'priority_changed', $userId, 'priority', $existing['priority'], $data['priority'],
                    "Priority changed from {$existing['priority']} to {$data['priority']}");
            }
        }

        // Assignment with history logging
        if (array_key_exists('assigned_to', $data)) {
            $newAssignee = $data['assigned_to'] !== null ? (int) $data['assigned_to'] : null;
            $fields[] = 'assigned_to = ?';
            $params[] = $newAssignee;

            $oldAssignee = $existing['assigned_to'] ? (string) $existing['assigned_to'] : 'unassigned';
            $newAssigneeStr = $newAssignee !== null ? (string) $newAssignee : 'unassigned';

            if ($oldAssignee !== $newAssigneeStr) {
                $this->logHistory($tenantId, $id, 'assignment_changed', $userId, 'assigned_to', $oldAssignee, $newAssigneeStr,
                    "Assignment changed");
            }
        }

        // Integer fields
        $intFields = ['assigned_group_id', 'progress_percentage', 'parent_deliverable_id', 'delivery_confidence'];
        foreach ($intFields as $fieldName) {
            if (array_key_exists($fieldName, $data)) {
                $fields[] = "{$fieldName} = ?";
                $params[] = $data[$fieldName] !== null ? (int) $data[$fieldName] : null;
            }
        }

        // Float fields
        $floatFields = ['estimated_hours', 'actual_hours'];
        foreach ($floatFields as $fieldName) {
            if (array_key_exists($fieldName, $data)) {
                $fields[] = "{$fieldName} = ?";
                $params[] = $data[$fieldName] !== null ? (float) $data[$fieldName] : null;
            }
        }

        // JSON fields
        $jsonFields = [
            'blocking_deliverable_ids', 'depends_on_deliverable_ids', 'tags',
            'custom_fields', 'watchers', 'collaborators', 'attachment_urls', 'external_links',
        ];
        foreach ($jsonFields as $fieldName) {
            if (array_key_exists($fieldName, $data)) {
                $fields[] = "{$fieldName} = ?";
                $params[] = json_encode($data[$fieldName]);
            }
        }

        if (empty($fields)) {
            $this->respondWithError('VALIDATION_NO_FIELDS', 'No fields provided to update', null, 400);
            return;
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;

        $setClause = implode(', ', $fields);

        Database::query(
            "UPDATE deliverables SET {$setClause} WHERE id = ? AND tenant_id = ?",
            $params
        );

        // Return the updated deliverable
        $this->getDeliverable();
    }

    /**
     * DELETE /api/v2/admin/deliverability/{id}
     *
     * Delete a deliverable and its related milestones, comments, and history.
     */
    public function deleteDeliverable(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $id = $this->extractDeliverableId();
        if (!$id) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Deliverable ID is required', 'id', 400);
            return;
        }

        // Verify deliverable exists and belongs to tenant
        $deliverable = Database::query(
            "SELECT id, title FROM deliverables WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$deliverable) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Deliverable not found', null, 404);
            return;
        }

        // Delete related records
        Database::query(
            "DELETE FROM deliverable_comments WHERE deliverable_id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        Database::query(
            "DELETE FROM deliverable_milestones WHERE deliverable_id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        Database::query(
            "DELETE FROM deliverable_history WHERE deliverable_id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        // Delete the deliverable
        Database::query(
            "DELETE FROM deliverables WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    // ============================================
    // COMMENTS
    // ============================================

    /**
     * POST /api/v2/admin/deliverability/{id}/comments
     *
     * Add a comment to a deliverable.
     */
    public function addComment(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $id = $this->extractDeliverableId();
        if (!$id) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Deliverable ID is required', 'id', 400);
            return;
        }

        // Verify deliverable exists and belongs to tenant
        $deliverable = Database::query(
            "SELECT id FROM deliverables WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$deliverable) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Deliverable not found', null, 404);
            return;
        }

        $data = $this->getAllInput();
        $commentText = trim($data['comment_text'] ?? '');

        if (empty($commentText)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Comment text is required',
                'comment_text',
                400
            );
            return;
        }

        $userId = $this->getAuthUserId() ?? $adminId;
        $commentType = $data['comment_type'] ?? 'comment';
        $parentCommentId = isset($data['parent_comment_id']) ? (int) $data['parent_comment_id'] : null;
        $mentionedUserIds = isset($data['mentioned_user_ids']) ? json_encode($data['mentioned_user_ids']) : '[]';

        Database::query(
            "INSERT INTO deliverable_comments
                (tenant_id, deliverable_id, user_id, comment_text, comment_type,
                 parent_comment_id, reactions, is_pinned, is_edited, is_deleted,
                 mentioned_user_ids, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, '[]', 0, 0, 0, ?, NOW(), NOW())",
            [
                $tenantId,
                $id,
                $userId,
                $commentText,
                $commentType,
                $parentCommentId,
                $mentionedUserIds,
            ]
        );

        $commentId = Database::lastInsertId();

        // Log history
        $this->logHistory(
            $tenantId,
            $id,
            'comment_added',
            $userId,
            null,
            null,
            null,
            'Comment added'
        );

        // Fetch the newly created comment with user info
        $comment = Database::query(
            "SELECT c.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                    u.avatar_url as user_avatar
             FROM deliverable_comments c
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.id = ? AND c.tenant_id = ?",
            [$commentId, $tenantId]
        )->fetch();

        $this->respondWithData([
            'id' => (int) $comment['id'],
            'deliverable_id' => $id,
            'user_id' => (int) ($comment['user_id'] ?? 0),
            'user_name' => trim($comment['user_name'] ?? ''),
            'user_avatar' => $comment['user_avatar'] ?? null,
            'comment_text' => $comment['comment_text'] ?? '',
            'comment_type' => $comment['comment_type'] ?? 'comment',
            'parent_comment_id' => $comment['parent_comment_id'] ? (int) $comment['parent_comment_id'] : null,
            'reactions' => json_decode($comment['reactions'] ?? '[]', true) ?: [],
            'is_pinned' => (bool) ($comment['is_pinned'] ?? false),
            'is_edited' => false,
            'mentioned_user_ids' => json_decode($comment['mentioned_user_ids'] ?? '[]', true) ?: [],
            'created_at' => $comment['created_at'],
            'updated_at' => $comment['updated_at'] ?? null,
        ], null, 201);
    }
}
