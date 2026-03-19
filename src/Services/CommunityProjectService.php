<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * CommunityProjectService
 *
 * Manages community-proposed volunteering projects. Members can propose
 * projects, gather community support (upvotes), and admins can review
 * and convert approved proposals into formal volunteering opportunities.
 *
 * Tables: vol_community_projects, vol_community_project_supporters
 */
class CommunityProjectService
{
    private static array $errors = [];

    /** @var string[] Valid project statuses (must match DB ENUM) */
    private const VALID_STATUSES = [
        'proposed',
        'under_review',
        'approved',
        'rejected',
        'active',
        'completed',
        'cancelled',
    ];

    /** @var string[] Statuses an admin can set during review */
    private const REVIEW_STATUSES = ['approved', 'rejected', 'under_review'];

    /**
     * Get validation errors
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Submit a new community project proposal.
     *
     * @param int   $userId Proposer user ID
     * @param array $data   Required: title, description. Optional: category, location, lat, lng,
     *                      target_volunteers, proposed_date, skills_needed, estimated_hours
     * @return array Created project record (empty on validation failure)
     */
    public static function propose(int $userId, array $data): array
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');

        if ($title === '') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required', 'field' => 'title'];
            return [];
        }

        if ($description === '') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Description is required', 'field' => 'description'];
            return [];
        }

        $category = isset($data['category']) ? trim($data['category']) : null;
        $location = isset($data['location']) ? trim($data['location']) : null;
        $lat = isset($data['lat']) ? (float)$data['lat'] : (isset($data['latitude']) ? (float)$data['latitude'] : null);
        $lng = isset($data['lng']) ? (float)$data['lng'] : (isset($data['longitude']) ? (float)$data['longitude'] : null);
        $targetVolunteers = isset($data['target_volunteers']) ? (int)$data['target_volunteers'] : null;
        $proposedDate = isset($data['proposed_date']) ? trim($data['proposed_date']) : null;
        $skillsNeeded = isset($data['skills_needed'])
            ? (is_array($data['skills_needed']) ? json_encode($data['skills_needed']) : trim($data['skills_needed']))
            : null;
        $estimatedHours = isset($data['estimated_hours']) ? (float)$data['estimated_hours'] : null;

        // Validate proposed_date format if provided
        if ($proposedDate !== null && $proposedDate !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d', $proposedDate);
            if (!$parsed || $parsed->format('Y-m-d') !== $proposedDate) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'proposed_date must be a valid date (YYYY-MM-DD)', 'field' => 'proposed_date'];
                return [];
            }
        } else {
            $proposedDate = null;
        }

        $sql = "
            INSERT INTO vol_community_projects
                (tenant_id, proposed_by, title, description, category, location,
                 latitude, longitude, target_volunteers, proposed_date, skills_needed,
                 estimated_hours, status, supporter_count, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'proposed', 0, NOW(), NOW())
        ";

        Database::query($sql, [
            $tenantId,
            $userId,
            $title,
            $description,
            $category,
            $location,
            $lat,
            $lng,
            $targetVolunteers,
            $proposedDate,
            $skillsNeeded,
            $estimatedHours,
        ]);

        $projectId = (int)Database::lastInsertId();

        // Dispatch webhook
        try {
            if (class_exists(WebhookDispatchService::class)) {
                WebhookDispatchService::dispatch('project.proposed', [
                    'project_id' => $projectId,
                    'proposed_by' => $userId,
                    'title' => $title,
                ]);
            }
        } catch (\Throwable $e) {
            error_log("CommunityProjectService::propose webhook dispatch failed: " . $e->getMessage());
        }

        return self::getProposal($projectId) ?? [
            'id' => $projectId,
            'tenant_id' => $tenantId,
            'title' => $title,
            'status' => 'proposed',
        ];
    }

    /**
     * Get project proposals with cursor-based pagination.
     *
     * @param array $filters [status?, category?, search?, proposed_by?, sort?, cursor?, limit?]
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getProposals(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = min((int)($filters['limit'] ?? 20), 50);
        $cursor = $filters['cursor'] ?? null;
        $sort = $filters['sort'] ?? 'newest';

        $params = [$tenantId];
        $where = ['p.tenant_id = ?'];

        // Status filter
        $status = $filters['status'] ?? null;
        if ($status && in_array($status, self::VALID_STATUSES, true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        // Category filter
        if (!empty($filters['category'])) {
            $where[] = 'p.category = ?';
            $params[] = $filters['category'];
        }

        // Proposed by filter
        if (!empty($filters['proposed_by'])) {
            $where[] = 'p.proposed_by = ?';
            $params[] = (int)$filters['proposed_by'];
        }

        // Search filter (title + description)
        if (!empty($filters['search'])) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']);
            $term = '%' . $escaped . '%';
            $where[] = '(p.title LIKE ? OR p.description LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        // Cursor pagination (descending by id)
        if ($cursor) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false && is_numeric($cursorId)) {
                $where[] = 'p.id < ?';
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        // Sort order
        $orderBy = ($sort === 'most_supported')
            ? 'p.supporter_count DESC, p.id DESC'
            : 'p.created_at DESC, p.id DESC';

        $sql = "
            SELECT
                p.*,
                u.first_name AS proposer_first_name,
                u.last_name AS proposer_last_name,
                u.avatar_url AS proposer_avatar,
                (SELECT COUNT(*) FROM vol_community_project_supporters s2
                    WHERE s2.project_id = p.id) AS supporter_count
            FROM vol_community_projects p
            LEFT JOIN users u ON p.proposed_by = u.id AND u.tenant_id = p.tenant_id
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT ?
        ";

        $items = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        // Format items
        foreach ($items as &$item) {
            $item = self::formatProject($item);
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single proposal with supporter count and proposer info.
     */
    public static function getProposal(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $sql = "
            SELECT
                p.*,
                u.first_name AS proposer_first_name,
                u.last_name AS proposer_last_name,
                u.avatar_url AS proposer_avatar,
                (SELECT COUNT(*) FROM vol_community_project_supporters s2
                    WHERE s2.project_id = p.id) AS supporter_count
            FROM vol_community_projects p
            LEFT JOIN users u ON p.proposed_by = u.id AND u.tenant_id = p.tenant_id
            WHERE p.id = ? AND p.tenant_id = ?
        ";

        $row = Database::query($sql, [$id, $tenantId])->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return self::formatProject($row);
    }

    /**
     * Update a project proposal (only by proposer or admin).
     */
    public static function updateProposal(int $id, int $userId, array $data): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $existing = Database::query(
            "SELECT id, proposed_by, status FROM vol_community_projects WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$existing) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Project not found'];
            return false;
        }

        // Check permissions: must be proposer or admin
        if ((int)$existing['proposed_by'] !== $userId) {
            $userRole = Database::query(
                "SELECT role FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetchColumn();
            if (!in_array($userRole, ['admin', 'super_admin'], true)) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to update this project'];
                return false;
            }
        }

        $sets = [];
        $params = [];

        $allowedFields = [
            'title' => 'string',
            'description' => 'string',
            'category' => 'string',
            'location' => 'string',
            'lat' => 'float',
            'lng' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'target_volunteers' => 'int',
            'proposed_date' => 'date',
            'skills_needed' => 'string',
            'estimated_hours' => 'float',
        ];

        foreach ($allowedFields as $field => $type) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            // Map lat/lng to latitude/longitude column names
            $column = $field;
            if ($field === 'lat') {
                $column = 'latitude';
            }
            if ($field === 'lng') {
                $column = 'longitude';
            }

            $value = $data[$field];

            switch ($type) {
                case 'string':
                    $value = $value !== null ? trim((string)$value) : null;
                    break;
                case 'float':
                    $value = $value !== null ? (float)$value : null;
                    break;
                case 'int':
                    $value = $value !== null ? (int)$value : null;
                    break;
                case 'date':
                    if ($value !== null && $value !== '') {
                        $parsed = \DateTime::createFromFormat('Y-m-d', trim($value));
                        if (!$parsed || $parsed->format('Y-m-d') !== trim($value)) {
                            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => "{$field} must be a valid date (YYYY-MM-DD)", 'field' => $field];
                            return false;
                        }
                        $value = trim($value);
                    } else {
                        $value = null;
                    }
                    break;
            }

            $sets[] = "{$column} = ?";
            $params[] = $value;
        }

        if (empty($sets)) {
            return true;
        }

        // Validate required fields are not blanked
        if (array_key_exists('title', $data) && trim((string)$data['title']) === '') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title cannot be empty', 'field' => 'title'];
            return false;
        }
        if (array_key_exists('description', $data) && trim((string)$data['description']) === '') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Description cannot be empty', 'field' => 'description'];
            return false;
        }

        $sets[] = "updated_at = NOW()";
        $params[] = $id;
        $params[] = $tenantId;

        $setClause = implode(', ', $sets);
        $sql = "UPDATE vol_community_projects SET {$setClause} WHERE id = ? AND tenant_id = ?";

        Database::query($sql, $params);

        return true;
    }

    /**
     * Admin reviews a proposed project (approve, reject, or mark under_review).
     *
     * On approval, auto-creates a vol_opportunity via convertToOpportunity().
     *
     * @param int         $id         Project ID
     * @param int         $reviewerId The admin performing the review
     * @param string      $status     One of: approved, rejected, under_review
     * @param string|null $notes      Optional review notes
     * @return bool
     */
    public static function review(int $id, int $reviewerId, string $status, ?string $notes = null): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        if (!in_array($status, self::REVIEW_STATUSES, true)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Status must be one of: ' . implode(', ', self::REVIEW_STATUSES), 'field' => 'status'];
            return false;
        }

        // Verify the project exists and is reviewable
        $project = Database::query(
            "SELECT id, status, title, proposed_by FROM vol_community_projects WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$project) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Project not found'];
            return false;
        }

        $reviewableStatuses = ['proposed', 'pending', 'under_review'];
        if (!in_array($project['status'], $reviewableStatuses, true)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Project can only be reviewed when in proposed/pending/under_review status'];
            return false;
        }

        Database::query(
            "UPDATE vol_community_projects
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$status, $reviewerId, $notes, $id, $tenantId]
        );

        // On approval, auto-create opportunity
        if ($status === 'approved') {
            $opportunityId = self::convertToOpportunity($id);

            // Dispatch webhook
            try {
                if (class_exists(WebhookDispatchService::class)) {
                    WebhookDispatchService::dispatch('project.approved', [
                        'project_id' => $id,
                        'opportunity_id' => $opportunityId,
                        'reviewed_by' => $reviewerId,
                        'title' => $project['title'],
                    ]);
                }
            } catch (\Throwable $e) {
                error_log("CommunityProjectService::review webhook dispatch failed: " . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Add a supporter to a project proposal.
     *
     * Uses INSERT IGNORE to handle duplicate support gracefully.
     * Increments the denormalized upvotes counter.
     */
    public static function support(int $projectId, int $userId, ?string $message = null): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        // Verify project exists in this tenant
        $exists = Database::query(
            "SELECT id FROM vol_community_projects WHERE id = ? AND tenant_id = ?",
            [$projectId, $tenantId]
        )->fetch();

        if (!$exists) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Project not found'];
            return false;
        }

        // INSERT IGNORE handles the unique constraint on (project_id, user_id)
        $stmt = Database::query(
            "INSERT IGNORE INTO vol_community_project_supporters
                (tenant_id, project_id, user_id, message, supported_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$tenantId, $projectId, $userId, $message]
        );

        $inserted = $stmt->rowCount() > 0;

        if ($inserted) {
            Database::query(
                "UPDATE vol_community_projects SET supporter_count = supporter_count + 1, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$projectId, $tenantId]
            );
        } else {
            self::$errors[] = ['code' => 'DUPLICATE', 'message' => 'You are already supporting this project'];
        }

        return $inserted;
    }

    /**
     * Remove support from a community project.
     */
    public static function unsupport(int $projectId, int $userId): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "DELETE FROM vol_community_project_supporters
             WHERE project_id = ? AND user_id = ? AND tenant_id = ?",
            [$projectId, $userId, $tenantId]
        );

        $deleted = $stmt->rowCount() > 0;

        if ($deleted) {
            Database::query(
                "UPDATE vol_community_projects SET supporter_count = GREATEST(supporter_count - 1, 0), updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$projectId, $tenantId]
            );
        } else {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'You are not supporting this project'];
        }

        return $deleted;
    }

    /**
     * Get all supporters for a project.
     */
    public static function getSupporters(int $projectId): array
    {
        $tenantId = TenantContext::getId();

        $sql = "
            SELECT s.*, u.first_name, u.last_name, u.avatar_url AS profile_pic
            FROM vol_community_project_supporters s
            LEFT JOIN users u ON s.user_id = u.id AND u.tenant_id = ?
            WHERE s.project_id = ? AND s.tenant_id = ?
            ORDER BY s.supported_at DESC
        ";

        return Database::query($sql, [$tenantId, $projectId, $tenantId])
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Convert an approved community project into a vol_opportunity.
     *
     * Creates a vol_opportunity from the project data, updates the project
     * with the opportunity_id, and sets status to 'active'.
     *
     * @return int|null The new opportunity ID, or null if conversion failed
     */
    public static function convertToOpportunity(int $projectId): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $project = Database::query(
            "SELECT * FROM vol_community_projects WHERE id = ? AND tenant_id = ?",
            [$projectId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$project) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Project not found'];
            return null;
        }

        if (!in_array($project['status'], ['approved', 'active'], true)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Only approved projects can be converted to opportunities'];
            return null;
        }

        try {
            // Create the volunteering opportunity from project data
            Database::query(
                "INSERT INTO vol_opportunities
                    (tenant_id, organization_id, title, description, location,
                     latitude, longitude, skills_needed,
                     start_date, status, is_active, created_by, created_at)
                 VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, 'open', 1, ?, NOW())",
                [
                    $tenantId,
                    $project['title'],
                    $project['description'],
                    $project['location'],
                    $project['latitude'],
                    $project['longitude'],
                    $project['skills_needed'] ?? null,
                    $project['proposed_date'],
                    (int)$project['proposed_by'],
                ]
            );

            $opportunityId = (int)Database::lastInsertId();

            // Update the project with the opportunity reference and set status to active
            Database::query(
                "UPDATE vol_community_projects
                 SET opportunity_id = ?, status = 'active', updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$opportunityId, $projectId, $tenantId]
            );

            return $opportunityId;
        } catch (\Throwable $e) {
            error_log("CommunityProjectService::convertToOpportunity failed: " . $e->getMessage());
            self::$errors[] = ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to create opportunity from project'];
            return null;
        }
    }

    /**
     * Format a raw project row for API response.
     */
    private static function formatProject(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'tenant_id' => (int)$row['tenant_id'],
            'proposed_by' => (int)$row['proposed_by'],
            'title' => $row['title'],
            'description' => $row['description'],
            'category' => $row['category'] ?? null,
            'location' => $row['location'] ?? null,
            'latitude' => isset($row['latitude']) && $row['latitude'] !== null ? (float)$row['latitude'] : null,
            'longitude' => isset($row['longitude']) && $row['longitude'] !== null ? (float)$row['longitude'] : null,
            'target_volunteers' => isset($row['target_volunteers']) && $row['target_volunteers'] !== null ? (int)$row['target_volunteers'] : null,
            'proposed_date' => $row['proposed_date'] ?? null,
            'skills_needed' => $row['skills_needed'] ?? null,
            'estimated_hours' => isset($row['estimated_hours']) && $row['estimated_hours'] !== null ? (float)$row['estimated_hours'] : null,
            'status' => $row['status'],
            'reviewed_by' => isset($row['reviewed_by']) && $row['reviewed_by'] !== null ? (int)$row['reviewed_by'] : null,
            'reviewed_at' => $row['reviewed_at'] ?? null,
            'review_notes' => $row['review_notes'] ?? null,
            'opportunity_id' => isset($row['opportunity_id']) && $row['opportunity_id'] !== null ? (int)$row['opportunity_id'] : null,
            'upvotes' => (int)($row['supporter_count'] ?? 0),
            'supporter_count' => (int)($row['supporter_count'] ?? 0),
            'user_has_supported' => (bool)($row['user_has_supported'] ?? false),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'] ?? null,
            'proposer' => [
                'first_name' => $row['proposer_first_name'] ?? null,
                'last_name' => $row['proposer_last_name'] ?? null,
                'avatar_url' => $row['proposer_avatar'] ?? null,
            ],
        ];
    }
}
