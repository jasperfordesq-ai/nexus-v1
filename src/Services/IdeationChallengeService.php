<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Services\GroupService;
use Nexus\Services\ChallengeTagService;
use Nexus\Services\IdeaMediaService;
use Nexus\Services\IdeaTeamConversionService;

/**
 * IdeationChallengeService - Business logic for ideation challenges
 *
 * Provides methods for challenges, ideas, votes, and comments with
 * standardized error handling and tenant scoping.
 *
 * @package Nexus\Services
 */
class IdeationChallengeService
{
    /** @var array Collected errors */
    private static array $errors = [];

    /**
     * Get all validation errors
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Clear errors
     */
    private static function clearErrors(): void
    {
        self::$errors = [];
    }

    /**
     * Add an error
     */
    private static function addError(string $code, string $message, ?string $field = null): void
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if ($field !== null) {
            $error['field'] = $field;
        }
        self::$errors[] = $error;
    }

    /**
     * Check if user has admin role
     */
    private static function isAdmin(int $userId): bool
    {
        $tenantId = TenantContext::getId();
        $user = Database::query(
            "SELECT role FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user) {
            return false;
        }

        return in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin']);
    }

    // ============================================
    // CHALLENGE METHODS
    // ============================================

    /**
     * Get all challenges with filtering and cursor-based pagination
     *
     * @param array $filters Optional: status, cursor, limit
     * @return array ['items' => [...], 'cursor' => ?string, 'has_more' => bool]
     */
    public static function getAllChallenges(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;
        $status = $filters['status'] ?? null;
        $userId = $filters['user_id'] ?? null;

        $params = [$tenantId];
        $where = ["c.tenant_id = ?"];

        // Status filter
        if ($status && in_array($status, ['draft', 'open', 'voting', 'evaluating', 'closed', 'archived'])) {
            $where[] = "c.status = ?";
            $params[] = $status;
        }

        // Block draft challenges for non-admin users
        if ($status === 'draft') {
            if (!$userId || !self::isAdmin($userId)) {
                $where[] = "1 = 0"; // Return no results for non-admins requesting drafts
            }
        }

        // Category filter
        $categoryId = $filters['category_id'] ?? null;
        if ($categoryId) {
            $where[] = "c.category_id = ?";
            $params[] = (int)$categoryId;
        }

        // Favorites filter (show only user's favorites)
        $favoritesOnly = $filters['favorites_only'] ?? false;
        if ($favoritesOnly && $userId) {
            $where[] = "EXISTS (SELECT 1 FROM challenge_favorites cf WHERE cf.challenge_id = c.id AND cf.user_id = ?)";
            $params[] = $userId;
        }

        // Full-text search on title and description
        $search = $filters['search'] ?? null;
        if ($search) {
            $where[] = "(c.title LIKE ? OR c.description LIKE ?)";
            $escapedSearch = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $searchTerm = '%' . $escapedSearch . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Tag filter — match by tag name through the pivot table
        $tags = $filters['tags'] ?? null;
        if ($tags && is_array($tags) && count($tags) > 0) {
            $tagPlaceholders = implode(',', array_fill(0, count($tags), '?'));
            $where[] = "EXISTS (
                SELECT 1 FROM challenge_tag_links ctl
                INNER JOIN challenge_tags ct ON ctl.tag_id = ct.id
                WHERE ctl.challenge_id = c.id AND ct.name IN ({$tagPlaceholders})
            )";
            $params = array_merge($params, $tags);
        }

        // Cursor pagination
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
                u.first_name AS creator_first_name,
                u.last_name AS creator_last_name,
                u.avatar_url AS creator_avatar
            FROM ideation_challenges c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE {$whereClause}
            ORDER BY c.created_at DESC, c.id DESC
            LIMIT ?
        ";

        $items = Database::query($sql, $params)->fetchAll();

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        // Format each item
        foreach ($items as &$item) {
            $item = self::formatChallenge($item, $userId);
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get all unique tags used across challenges for this tenant.
     *
     * Returns tag names with usage counts, ordered by popularity then alphabetically.
     *
     * @return array<array{tag: string, count: int}>
     */
    public static function getAllTags(): array
    {
        $tenantId = TenantContext::getId();

        $sql = "
            SELECT ct.name AS tag, COUNT(*) AS count
            FROM challenge_tag_links ctl
            INNER JOIN challenge_tags ct ON ctl.tag_id = ct.id
            INNER JOIN ideation_challenges c ON ctl.challenge_id = c.id
            WHERE c.tenant_id = ?
            GROUP BY ct.name
            ORDER BY count DESC, ct.name ASC
        ";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Get a single challenge by ID
     */
    public static function getChallengeById(int $id, ?int $userId = null): ?array
    {
        $tenantId = TenantContext::getId();

        $sql = "
            SELECT
                c.*,
                u.first_name AS creator_first_name,
                u.last_name AS creator_last_name,
                u.avatar_url AS creator_avatar
            FROM ideation_challenges c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.id = ? AND c.tenant_id = ?
        ";

        $challenge = Database::query($sql, [$id, $tenantId])->fetch();

        if (!$challenge) {
            return null;
        }

        $challenge = self::formatChallenge($challenge, $userId);

        // Add user's idea count for this challenge
        if ($userId) {
            $userIdeaCount = Database::query(
                "SELECT COUNT(*) AS cnt FROM challenge_ideas WHERE challenge_id = ? AND user_id = ? AND status NOT IN ('draft', 'withdrawn')",
                [$id, $userId]
            )->fetch();
            $challenge['user_idea_count'] = (int)($userIdeaCount['cnt'] ?? 0);
        }

        return $challenge;
    }

    /**
     * Format challenge with creator info and enriched fields
     *
     * @param array $challenge Raw challenge data from DB
     * @param int|null $userId Optional user ID for is_favorited check
     * @return array Formatted challenge
     */
    private static function formatChallenge(array $challenge, ?int $userId = null): array
    {
        $challenge['creator'] = [
            'id' => (int)$challenge['user_id'],
            'name' => trim(($challenge['creator_first_name'] ?? '') . ' ' . ($challenge['creator_last_name'] ?? '')),
            'avatar_url' => $challenge['creator_avatar'] ?? null,
        ];

        $challenge['ideas_count'] = (int)($challenge['ideas_count'] ?? 0);
        $challenge['favorites_count'] = (int)($challenge['favorites_count'] ?? 0);
        $challenge['views_count'] = (int)($challenge['views_count'] ?? 0);
        $challenge['is_featured'] = (bool)($challenge['is_featured'] ?? false);
        $challenge['cover_image'] = $challenge['cover_image'] ?? null;

        // Decode legacy JSON tags to array (backward-compatible)
        if (isset($challenge['tags']) && is_string($challenge['tags'])) {
            $decoded = json_decode($challenge['tags'], true);
            $challenge['tags'] = is_array($decoded) ? $decoded : [];
        } else {
            $challenge['tags'] = [];
        }

        // Merge in normalized tags from challenge_tag_links
        try {
            $normalizedTags = ChallengeTagService::getTagsForChallenge((int)$challenge['id']);
            $tagNames = array_map(fn($t) => $t['name'], $normalizedTags);
            // Merge with legacy tags, deduplicate
            $challenge['tags'] = array_values(array_unique(array_merge($challenge['tags'], $tagNames)));
            $challenge['normalized_tags'] = $normalizedTags;
        } catch (\Throwable $e) {
            $challenge['normalized_tags'] = [];
        }

        // Resolve category from category_id
        $challenge['category_id'] = $challenge['category_id'] ?? null;
        if ($challenge['category_id']) {
            try {
                $cat = ChallengeCategoryService::getById((int)$challenge['category_id']);
                $challenge['category_data'] = $cat;
            } catch (\Throwable $e) {
                $challenge['category_data'] = null;
            }
        } else {
            $challenge['category_data'] = null;
        }

        // Decode evaluation_criteria JSON
        if (isset($challenge['evaluation_criteria']) && is_string($challenge['evaluation_criteria'])) {
            $decoded = json_decode($challenge['evaluation_criteria'], true);
            $challenge['evaluation_criteria'] = is_array($decoded) ? $decoded : [];
        } else {
            $challenge['evaluation_criteria'] = $challenge['evaluation_criteria'] ?? [];
        }

        // Check if current user has favorited this challenge
        if ($userId) {
            $challenge['is_favorited'] = self::isFavorited((int)$challenge['id'], $userId);
        } else {
            $challenge['is_favorited'] = false;
        }

        unset(
            $challenge['creator_first_name'],
            $challenge['creator_last_name'],
            $challenge['creator_avatar']
        );

        return $challenge;
    }

    /**
     * Create a new challenge
     *
     * @return int|null Challenge ID on success, null on failure
     */
    public static function createChallenge(int $userId, array $data): ?int
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can create challenges');
            return null;
        }

        $tenantId = TenantContext::getId();
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');

        if (empty($title)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title is required', 'title');
        }

        if (empty($description)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Description is required', 'description');
        }

        if (!empty(self::$errors)) {
            return null;
        }

        $category = !empty($data['category']) ? trim($data['category']) : null;
        $status = $data['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'open'])) {
            $status = 'draft';
        }
        $submissionDeadline = $data['submission_deadline'] ?? null;
        $votingDeadline = $data['voting_deadline'] ?? null;
        $prizeDescription = !empty($data['prize_description']) ? trim($data['prize_description']) : null;
        $maxIdeasPerUser = isset($data['max_ideas_per_user']) ? (int)$data['max_ideas_per_user'] : null;
        $tags = isset($data['tags']) && is_array($data['tags']) ? json_encode($data['tags']) : null;
        $coverImage = !empty($data['cover_image']) ? trim($data['cover_image']) : null;
        $categoryId = isset($data['category_id']) ? (int)$data['category_id'] : null;
        $evaluationCriteria = isset($data['evaluation_criteria']) && is_array($data['evaluation_criteria'])
            ? json_encode($data['evaluation_criteria'])
            : null;

        try {
            Database::query(
                "INSERT INTO ideation_challenges
                    (tenant_id, user_id, title, description, category, category_id, status, submission_deadline, voting_deadline, prize_description, max_ideas_per_user, tags, cover_image, evaluation_criteria, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $tenantId,
                    $userId,
                    $title,
                    $description,
                    $category,
                    $categoryId,
                    $status,
                    $submissionDeadline,
                    $votingDeadline,
                    $prizeDescription,
                    $maxIdeasPerUser,
                    $tags,
                    $coverImage,
                    $evaluationCriteria,
                ]
            );

            $challengeId = Database::lastInsertId();

            // Record in feed_activity table
            try {
                FeedActivityService::recordActivity($tenantId, $userId, 'challenge', (int)$challengeId, [
                    'title' => $title,
                    'content' => $description,
                    'image_url' => $coverImage,
                    'metadata' => [
                        'submission_deadline' => $submissionDeadline,
                        'ideas_count' => 0,
                    ],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $faEx) {
                error_log("IdeationChallengeService::createChallenge feed_activity record failed: " . $faEx->getMessage());
            }

            // Sync normalized tags if tag_ids provided
            if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
                ChallengeTagService::syncTagsForChallenge((int)$challengeId, $data['tag_ids']);
            } elseif (isset($data['tags']) && is_array($data['tags'])) {
                // Auto-create tags from legacy tag names and sync
                $tagIds = ChallengeTagService::findOrCreateByNames($data['tags']);
                if (!empty($tagIds)) {
                    ChallengeTagService::syncTagsForChallenge((int)$challengeId, $tagIds);
                }
            }

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 10, 'Created an ideation challenge');
                }
            } catch (\Throwable $e) {
                // Gamification is optional
            }

            return (int)$challengeId;
        } catch (\Throwable $e) {
            error_log("Challenge creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create challenge');
            return null;
        }
    }

    /**
     * Update an existing challenge
     */
    public static function updateChallenge(int $id, int $userId, array $data): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can update challenges');
            return false;
        }

        $challenge = self::getChallengeById($id);

        if (!$challenge) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Challenge not found');
            return false;
        }

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

        if (isset($data['description'])) {
            $description = trim($data['description']);
            if (empty($description)) {
                self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Description cannot be empty', 'description');
                return false;
            }
            $updates[] = "description = ?";
            $params[] = $description;
        }

        if (array_key_exists('category', $data)) {
            $updates[] = "category = ?";
            $params[] = !empty($data['category']) ? trim($data['category']) : null;
        }

        if (array_key_exists('submission_deadline', $data)) {
            $updates[] = "submission_deadline = ?";
            $params[] = $data['submission_deadline'];
        }

        if (array_key_exists('voting_deadline', $data)) {
            $updates[] = "voting_deadline = ?";
            $params[] = $data['voting_deadline'];
        }

        if (array_key_exists('prize_description', $data)) {
            $updates[] = "prize_description = ?";
            $params[] = !empty($data['prize_description']) ? trim($data['prize_description']) : null;
        }

        if (array_key_exists('max_ideas_per_user', $data)) {
            $updates[] = "max_ideas_per_user = ?";
            $params[] = $data['max_ideas_per_user'] !== null ? (int)$data['max_ideas_per_user'] : null;
        }

        if (array_key_exists('tags', $data)) {
            $updates[] = "tags = ?";
            $params[] = is_array($data['tags']) ? json_encode($data['tags']) : null;
        }

        if (array_key_exists('cover_image', $data)) {
            $updates[] = "cover_image = ?";
            $params[] = !empty($data['cover_image']) ? trim($data['cover_image']) : null;
        }

        if (array_key_exists('category_id', $data)) {
            $updates[] = "category_id = ?";
            $params[] = $data['category_id'] !== null ? (int)$data['category_id'] : null;
        }

        if (array_key_exists('evaluation_criteria', $data)) {
            $updates[] = "evaluation_criteria = ?";
            $params[] = is_array($data['evaluation_criteria']) ? json_encode($data['evaluation_criteria']) : null;
        }

        if (empty($updates)) {
            // Still check for tag sync even if no column updates
            if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
                ChallengeTagService::syncTagsForChallenge($id, $data['tag_ids']);
            } elseif (isset($data['tags']) && is_array($data['tags'])) {
                $tagIds = ChallengeTagService::findOrCreateByNames($data['tags']);
                ChallengeTagService::syncTagsForChallenge($id, $tagIds);
            }
            return true;
        }

        $tenantId = TenantContext::getId();
        $params[] = $id;
        $params[] = $tenantId;

        try {
            Database::query(
                "UPDATE ideation_challenges SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
                $params
            );

            // Sync normalized tags
            if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
                ChallengeTagService::syncTagsForChallenge($id, $data['tag_ids']);
            } elseif (isset($data['tags']) && is_array($data['tags'])) {
                $tagIds = ChallengeTagService::findOrCreateByNames($data['tags']);
                ChallengeTagService::syncTagsForChallenge($id, $tagIds);
            }

            return true;
        } catch (\Throwable $e) {
            error_log("Challenge update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update challenge');
            return false;
        }
    }

    /**
     * Delete a challenge
     */
    public static function deleteChallenge(int $id, int $userId): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can delete challenges');
            return false;
        }

        $challenge = self::getChallengeById($id);

        if (!$challenge) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Challenge not found');
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            // FK cascade will delete ideas, votes, comments
            Database::query(
                "DELETE FROM ideation_challenges WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            // Remove from feed_activity
            try {
                FeedActivityService::removeActivity('challenge', $id);
            } catch (\Exception $faEx) {
                error_log("IdeationChallengeService::deleteChallenge feed_activity remove failed: " . $faEx->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            error_log("Challenge deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete challenge');
            return false;
        }
    }

    /**
     * Update challenge status (lifecycle: draft→open→voting→closed)
     */
    public static function updateChallengeStatus(int $id, int $userId, string $status): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can change challenge status');
            return false;
        }

        $validStatuses = ['draft', 'open', 'voting', 'evaluating', 'closed', 'archived'];
        if (!in_array($status, $validStatuses)) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid status value', 'status');
            return false;
        }

        $challenge = self::getChallengeById($id);

        if (!$challenge) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Challenge not found');
            return false;
        }

        // Validate status transitions
        // Lifecycle: Draft → Open → Voting → Evaluating → Closed → Archived
        // With shortcuts: Open can skip to Closed; Closed can reopen
        $currentStatus = $challenge['status'];
        $validTransitions = [
            'draft'      => ['open'],
            'open'       => ['voting', 'evaluating', 'closed'],
            'voting'     => ['evaluating', 'closed'],
            'evaluating' => ['closed'],
            'closed'     => ['open', 'archived'],
            'archived'   => ['closed'], // Allow un-archiving
        ];

        if (!in_array($status, $validTransitions[$currentStatus] ?? [])) {
            self::addError(
                ApiErrorCodes::RESOURCE_CONFLICT,
                "Cannot transition from '{$currentStatus}' to '{$status}'"
            );
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "UPDATE ideation_challenges SET status = ? WHERE id = ? AND tenant_id = ?",
                [$status, $id, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Challenge status update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update challenge status');
            return false;
        }
    }

    // ============================================
    // IDEA METHODS
    // ============================================

    /**
     * Get ideas for a challenge with filtering and cursor-based pagination
     *
     * @param int $challengeId
     * @param array $filters sort (votes|newest), cursor, limit, user_id
     * @return array
     */
    public static function getIdeas(int $challengeId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;
        $sort = $filters['sort'] ?? 'votes';
        $userId = $filters['user_id'] ?? null;

        // Verify challenge belongs to this tenant
        $challenge = Database::query(
            "SELECT id FROM ideation_challenges WHERE id = ? AND tenant_id = ?",
            [$challengeId, $tenantId]
        )->fetch();

        if (!$challenge) {
            return ['items' => [], 'cursor' => null, 'has_more' => false];
        }

        $params = [$challengeId];
        $where = ["i.challenge_id = ?"];

        // Exclude draft ideas from public listing (drafts are private to the author)
        $where[] = "i.status NOT IN ('draft', 'withdrawn')";

        // Cursor pagination
        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "i.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $orderBy = $sort === 'votes'
            ? "i.votes_count DESC, i.created_at DESC, i.id DESC"
            : "i.created_at DESC, i.id DESC";

        $sql = "
            SELECT
                i.*,
                u.first_name AS creator_first_name,
                u.last_name AS creator_last_name,
                u.avatar_url AS creator_avatar
            FROM challenge_ideas i
            LEFT JOIN users u ON i.user_id = u.id
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT ?
        ";

        $items = Database::query($sql, $params)->fetchAll();

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        // Format each idea
        foreach ($items as &$item) {
            $item = self::formatIdea($item, $userId);
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single idea by ID
     */
    public static function getIdeaById(int $id, ?int $userId = null): ?array
    {
        $tenantId = TenantContext::getId();

        $sql = "
            SELECT
                i.*,
                u.first_name AS creator_first_name,
                u.last_name AS creator_last_name,
                u.avatar_url AS creator_avatar
            FROM challenge_ideas i
            LEFT JOIN users u ON i.user_id = u.id
            JOIN ideation_challenges ic ON i.challenge_id = ic.id
            WHERE i.id = ? AND ic.tenant_id = ?
        ";

        $idea = Database::query($sql, [$id, $tenantId])->fetch();

        if (!$idea) {
            return null;
        }

        return self::formatIdea($idea, $userId);
    }

    /**
     * Format idea with creator info and vote check
     */
    private static function formatIdea(array $idea, ?int $userId = null): array
    {
        $idea['creator'] = [
            'id' => (int)$idea['user_id'],
            'name' => trim(($idea['creator_first_name'] ?? '') . ' ' . ($idea['creator_last_name'] ?? '')),
            'avatar_url' => $idea['creator_avatar'] ?? null,
        ];

        $idea['votes_count'] = (int)($idea['votes_count'] ?? 0);
        $idea['comments_count'] = (int)($idea['comments_count'] ?? 0);

        // Check if user has voted
        if ($userId) {
            $hasVoted = Database::query(
                "SELECT id FROM challenge_idea_votes WHERE idea_id = ? AND user_id = ?",
                [$idea['id'], $userId]
            )->fetch();
            $idea['has_voted'] = !empty($hasVoted);
        } else {
            $idea['has_voted'] = false;
        }

        // Attach media items
        try {
            $idea['media'] = IdeaMediaService::getMediaForIdea((int)$idea['id']);
        } catch (\Throwable $e) {
            $idea['media'] = [];
        }

        // Check for team conversion link
        try {
            $teamLink = IdeaTeamConversionService::getLinkForIdea((int)$idea['id']);
            $idea['team_link'] = $teamLink;
        } catch (\Throwable $e) {
            $idea['team_link'] = null;
        }

        unset(
            $idea['creator_first_name'],
            $idea['creator_last_name'],
            $idea['creator_avatar']
        );

        return $idea;
    }

    /**
     * Submit a new idea to a challenge
     *
     * @return int|null Idea ID on success, null on failure
     */
    public static function submitIdea(int $challengeId, int $userId, array $data): ?int
    {
        self::clearErrors();

        $challenge = self::getChallengeById($challengeId, $userId);

        if (!$challenge) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Challenge not found');
            return null;
        }

        // Challenge must be open
        if ($challenge['status'] !== 'open') {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Challenge is not open for submissions');
            return null;
        }

        // Check submission deadline
        if (!empty($challenge['submission_deadline']) && strtotime($challenge['submission_deadline']) < time()) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Submission deadline has passed');
            return null;
        }

        // Check max ideas per user
        if ($challenge['max_ideas_per_user'] !== null) {
            $userIdeaCount = $challenge['user_idea_count'] ?? 0;
            if ($userIdeaCount >= (int)$challenge['max_ideas_per_user']) {
                self::addError(
                    ApiErrorCodes::RESOURCE_CONFLICT,
                    'You have reached the maximum number of ideas for this challenge'
                );
                return null;
            }
        }

        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');

        // Determine if this is a draft save (check before validation)
        $isDraft = !empty($data['is_draft']);
        $status = $isDraft ? 'draft' : 'submitted';

        if (empty($title)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title is required', 'title');
        }

        // Description is required for submissions but optional for drafts
        if (!$isDraft && empty($description)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Description is required', 'description');
        }

        if (!empty(self::$errors)) {
            return null;
        }

        try {
            Database::beginTransaction();

            Database::query(
                "INSERT INTO challenge_ideas (challenge_id, user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                [$challengeId, $userId, $title, $description, $status]
            );

            $ideaId = Database::lastInsertId();

            // Only increment ideas_count and award points for submitted ideas (not drafts)
            if (!$isDraft) {
                $tenantId = TenantContext::getId();
                Database::query(
                    "UPDATE ideation_challenges SET ideas_count = ideas_count + 1 WHERE id = ? AND tenant_id = ?",
                    [$challengeId, $tenantId]
                );
            }

            Database::commit();

            // Award gamification points (only for submitted ideas)
            if (!$isDraft) {
                try {
                    if (class_exists('\Nexus\Models\Gamification')) {
                        \Nexus\Models\Gamification::awardPoints($userId, 5, 'Submitted an idea');
                    }
                } catch (\Throwable $e) {
                    // Gamification is optional
                }
            }

            return (int)$ideaId;
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Idea submission failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to submit idea');
            return null;
        }
    }

    /**
     * Update an idea (owner only, challenge must be open)
     */
    public static function updateIdea(int $id, int $userId, array $data): bool
    {
        self::clearErrors();

        $idea = self::getIdeaById($id);

        if (!$idea) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Idea not found');
            return false;
        }

        // Owner check
        if ((int)$idea['user_id'] !== $userId) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only edit your own ideas');
            return false;
        }

        // Check challenge is still open
        $tenantId = TenantContext::getId();
        $challenge = Database::query(
            "SELECT status FROM ideation_challenges WHERE id = ? AND tenant_id = ?",
            [$idea['challenge_id'], $tenantId]
        )->fetch();

        if (!$challenge || $challenge['status'] !== 'open') {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Challenge is no longer open for edits');
            return false;
        }

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

        if (isset($data['description'])) {
            $description = trim($data['description']);
            if (empty($description)) {
                self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Description cannot be empty', 'description');
                return false;
            }
            $updates[] = "description = ?";
            $params[] = $description;
        }

        if (empty($updates)) {
            return true;
        }

        $tenantId = TenantContext::getId();
        $params[] = $id;
        $params[] = $tenantId;

        try {
            Database::query(
                "UPDATE challenge_ideas SET " . implode(', ', $updates) . " WHERE id = ? AND challenge_id IN (SELECT id FROM ideation_challenges WHERE tenant_id = ?)",
                $params
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Idea update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update idea');
            return false;
        }
    }

    /**
     * Update a draft idea (only drafts can be edited this way).
     * Can also publish a draft by setting 'publish' => true.
     */
    public static function updateDraftIdea(int $ideaId, int $userId, array $data): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Fetch the idea with tenant scoping
        $idea = Database::query(
            "SELECT ci.*, c.tenant_id, c.id AS challenge_id_ref FROM challenge_ideas ci
             INNER JOIN ideation_challenges c ON ci.challenge_id = c.id
             WHERE ci.id = ? AND c.tenant_id = ? AND ci.user_id = ?",
            [$ideaId, $tenantId, $userId]
        )->fetch();

        if (!$idea) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Idea not found');
            return false;
        }

        if ($idea['status'] !== 'draft') {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Only draft ideas can be edited');
            return false;
        }

        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $publish = !empty($data['publish']);

        if (empty($title)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title is required', 'title');
        }

        // Description is required when publishing but optional for draft saves
        if ($publish && empty($description)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Description is required', 'description');
        }

        if (!empty(self::$errors)) {
            return false;
        }

        $newStatus = $publish ? 'submitted' : 'draft';

        try {
            Database::beginTransaction();

            Database::query(
                "UPDATE challenge_ideas SET title = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?",
                [$title, $description, $newStatus, $ideaId]
            );

            // If publishing, increment challenge ideas_count and award points
            if ($publish) {
                Database::query(
                    "UPDATE ideation_challenges SET ideas_count = ideas_count + 1 WHERE id = ? AND tenant_id = ?",
                    [$idea['challenge_id'], $tenantId]
                );
            }

            Database::commit();

            // Award gamification points when publishing
            if ($publish) {
                try {
                    if (class_exists('\Nexus\Models\Gamification')) {
                        \Nexus\Models\Gamification::awardPoints($userId, 5, 'Submitted an idea');
                    }
                } catch (\Throwable $e) {
                    // Gamification is optional
                }
            }

            return true;
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Draft idea update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update draft');
            return false;
        }
    }

    /**
     * Get user's draft ideas for a challenge
     */
    public static function getUserDrafts(int $challengeId, int $userId): array
    {
        $tenantId = TenantContext::getId();

        $sql = "
            SELECT ci.id, ci.title, ci.description, ci.status, ci.created_at, ci.updated_at
            FROM challenge_ideas ci
            INNER JOIN ideation_challenges c ON ci.challenge_id = c.id
            WHERE ci.challenge_id = ? AND ci.user_id = ? AND ci.status = 'draft' AND c.tenant_id = ?
            ORDER BY ci.updated_at DESC, ci.created_at DESC
        ";

        return Database::query($sql, [$challengeId, $userId, $tenantId])->fetchAll();
    }

    /**
     * Delete an idea (owner or admin)
     */
    public static function deleteIdea(int $id, int $userId): bool
    {
        self::clearErrors();

        $idea = self::getIdeaById($id);

        if (!$idea) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Idea not found');
            return false;
        }

        $isOwner = (int)$idea['user_id'] === $userId;
        $isAdmin = self::isAdmin($userId);

        if (!$isOwner && !$isAdmin) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only delete your own ideas');
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            Database::beginTransaction();

            $challengeId = (int)$idea['challenge_id'];

            // FK cascade will delete votes and comments
            Database::query(
                "DELETE FROM challenge_ideas WHERE id = ? AND challenge_id IN (SELECT id FROM ideation_challenges WHERE tenant_id = ?)",
                [$id, $tenantId]
            );

            // Decrement ideas_count on the challenge
            Database::query(
                "UPDATE ideation_challenges SET ideas_count = GREATEST(0, ideas_count - 1) WHERE id = ? AND tenant_id = ?",
                [$challengeId, $tenantId]
            );

            Database::commit();
            return true;
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Idea deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete idea');
            return false;
        }
    }

    /**
     * Toggle vote on an idea (insert or delete)
     *
     * @return array|null ['voted' => bool, 'votes_count' => int] on success, null on failure
     */
    public static function voteIdea(int $ideaId, int $userId): ?array
    {
        self::clearErrors();

        $idea = self::getIdeaById($ideaId, $userId);

        if (!$idea) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Idea not found');
            return null;
        }

        // Cannot vote on withdrawn or draft ideas
        if (in_array($idea['status'] ?? '', ['withdrawn', 'draft'])) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Cannot vote on a withdrawn or draft idea');
            return null;
        }

        // Check challenge is in open or voting status
        $tenantId = TenantContext::getId();
        $challenge = Database::query(
            "SELECT status FROM ideation_challenges WHERE id = ? AND tenant_id = ?",
            [$idea['challenge_id'], $tenantId]
        )->fetch();

        if (!$challenge || !in_array($challenge['status'], ['open', 'voting'])) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Voting is not currently allowed for this challenge');
            return null;
        }

        // Can't vote on your own idea
        if ((int)$idea['user_id'] === $userId) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'You cannot vote on your own idea');
            return null;
        }

        try {
            Database::beginTransaction();

            // Check existing vote
            $existingVote = Database::query(
                "SELECT id FROM challenge_idea_votes WHERE idea_id = ? AND user_id = ?",
                [$ideaId, $userId]
            )->fetch();

            if ($existingVote) {
                // Remove vote
                Database::query(
                    "DELETE FROM challenge_idea_votes WHERE idea_id = ? AND user_id = ?",
                    [$ideaId, $userId]
                );
                Database::query(
                    "UPDATE challenge_ideas SET votes_count = GREATEST(0, votes_count - 1) WHERE id = ?",
                    [$ideaId]
                );
                $voted = false;
            } else {
                // Add vote
                Database::query(
                    "INSERT INTO challenge_idea_votes (idea_id, user_id, created_at) VALUES (?, ?, NOW())",
                    [$ideaId, $userId]
                );
                Database::query(
                    "UPDATE challenge_ideas SET votes_count = votes_count + 1 WHERE id = ?",
                    [$ideaId]
                );
                $voted = true;
            }

            Database::commit();

            // Get updated vote count
            $updated = Database::query(
                "SELECT votes_count FROM challenge_ideas WHERE id = ?",
                [$ideaId]
            )->fetch();

            // Award gamification points for voting (only when adding a vote)
            if ($voted) {
                try {
                    if (class_exists('\Nexus\Models\Gamification')) {
                        \Nexus\Models\Gamification::awardPoints($userId, 1, 'Voted on an idea');
                    }
                } catch (\Throwable $e) {
                    // Gamification is optional
                }
            }

            return [
                'voted' => $voted,
                'votes_count' => (int)($updated['votes_count'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Idea vote failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to toggle vote');
            return null;
        }
    }

    /**
     * Update idea status (admin only: shortlist/winner)
     */
    public static function updateIdeaStatus(int $ideaId, int $userId, string $status): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can change idea status');
            return false;
        }

        $validStatuses = ['submitted', 'shortlisted', 'winner', 'withdrawn'];
        if (!in_array($status, $validStatuses)) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid status value', 'status');
            return false;
        }

        $idea = self::getIdeaById($ideaId);

        if (!$idea) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Idea not found');
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "UPDATE challenge_ideas SET status = ? WHERE id = ? AND challenge_id IN (SELECT id FROM ideation_challenges WHERE tenant_id = ?)",
                [$status, $ideaId, $tenantId]
            );

            // Award gamification points for winning
            if ($status === 'winner') {
                try {
                    if (class_exists('\Nexus\Models\Gamification')) {
                        \Nexus\Models\Gamification::awardPoints(
                            (int)$idea['user_id'],
                            25,
                            'Idea selected as winner'
                        );
                    }
                } catch (\Throwable $e) {
                    // Gamification is optional
                }
            }

            return true;
        } catch (\Throwable $e) {
            error_log("Idea status update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update idea status');
            return false;
        }
    }

    // ============================================
    // COMMENT METHODS
    // ============================================

    /**
     * Get comments for an idea with cursor-based pagination
     */
    public static function getComments(int $ideaId, array $filters = []): array
    {
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;

        // Verify idea exists and is in our tenant
        $idea = self::getIdeaById($ideaId);
        if (!$idea) {
            return ['items' => [], 'cursor' => null, 'has_more' => false];
        }

        $params = [$ideaId];
        $where = ["c.idea_id = ?"];

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
                u.first_name AS author_first_name,
                u.last_name AS author_last_name,
                u.avatar_url AS author_avatar
            FROM challenge_idea_comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE {$whereClause}
            ORDER BY c.created_at DESC, c.id DESC
            LIMIT ?
        ";

        $items = Database::query($sql, $params)->fetchAll();

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        foreach ($items as &$item) {
            $item['author'] = [
                'id' => (int)$item['user_id'],
                'name' => trim(($item['author_first_name'] ?? '') . ' ' . ($item['author_last_name'] ?? '')),
                'avatar_url' => $item['author_avatar'] ?? null,
            ];
            unset($item['author_first_name'], $item['author_last_name'], $item['author_avatar']);
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Add a comment to an idea
     *
     * @return int|null Comment ID on success, null on failure
     */
    public static function addComment(int $ideaId, int $userId, string $body): ?int
    {
        self::clearErrors();

        $body = trim($body);

        if (empty($body)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Comment body is required', 'body');
            return null;
        }

        $idea = self::getIdeaById($ideaId);

        if (!$idea) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Idea not found');
            return null;
        }

        // Cannot comment on withdrawn or draft ideas
        if (in_array($idea['status'] ?? '', ['withdrawn', 'draft'])) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Cannot comment on a withdrawn or draft idea');
            return null;
        }

        try {
            Database::beginTransaction();

            Database::query(
                "INSERT INTO challenge_idea_comments (idea_id, user_id, body, created_at) VALUES (?, ?, ?, NOW())",
                [$ideaId, $userId, $body]
            );

            $commentId = Database::lastInsertId();

            // Increment comments_count
            Database::query(
                "UPDATE challenge_ideas SET comments_count = comments_count + 1 WHERE id = ?",
                [$ideaId]
            );

            Database::commit();

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 2, 'Commented on an idea');
                }
            } catch (\Throwable $e) {
                // Gamification is optional
            }

            return (int)$commentId;
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Comment creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to add comment');
            return null;
        }
    }

    // ============================================
    // FAVORITE METHODS
    // ============================================

    /**
     * Toggle favorite on a challenge (insert or delete)
     *
     * @return array ['favorited' => bool, 'favorites_count' => int]
     */
    public static function toggleFavorite(int $challengeId, int $userId): array
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Verify challenge exists in this tenant
        $challenge = Database::query(
            "SELECT id FROM ideation_challenges WHERE id = ? AND tenant_id = ?",
            [$challengeId, $tenantId]
        )->fetch();

        if (!$challenge) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Challenge not found');
            return ['favorited' => false, 'favorites_count' => 0];
        }

        try {
            Database::beginTransaction();

            // Check existing favorite
            $existing = Database::query(
                "SELECT id FROM challenge_favorites WHERE challenge_id = ? AND user_id = ?",
                [$challengeId, $userId]
            )->fetch();

            if ($existing) {
                // Remove favorite
                Database::query(
                    "DELETE FROM challenge_favorites WHERE challenge_id = ? AND user_id = ?",
                    [$challengeId, $userId]
                );
                Database::query(
                    "UPDATE ideation_challenges SET favorites_count = GREATEST(0, favorites_count - 1) WHERE id = ? AND tenant_id = ?",
                    [$challengeId, $tenantId]
                );
                $favorited = false;
            } else {
                // Add favorite
                Database::query(
                    "INSERT INTO challenge_favorites (challenge_id, user_id, created_at) VALUES (?, ?, NOW())",
                    [$challengeId, $userId]
                );
                Database::query(
                    "UPDATE ideation_challenges SET favorites_count = favorites_count + 1 WHERE id = ? AND tenant_id = ?",
                    [$challengeId, $tenantId]
                );
                $favorited = true;
            }

            Database::commit();

            // Get updated count
            $updated = Database::query(
                "SELECT favorites_count FROM ideation_challenges WHERE id = ? AND tenant_id = ?",
                [$challengeId, $tenantId]
            )->fetch();

            return [
                'favorited' => $favorited,
                'favorites_count' => (int)($updated['favorites_count'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Challenge favorite toggle failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to toggle favorite');
            return ['favorited' => false, 'favorites_count' => 0];
        }
    }

    /**
     * Check if a user has favorited a challenge
     */
    public static function isFavorited(int $challengeId, int $userId): bool
    {
        $result = Database::query(
            "SELECT id FROM challenge_favorites WHERE challenge_id = ? AND user_id = ?",
            [$challengeId, $userId]
        )->fetch();

        return !empty($result);
    }

    // ============================================
    // DUPLICATE METHOD
    // ============================================

    /**
     * Duplicate a challenge as a draft copy
     *
     * Clones the challenge with status='draft', reset counts, and no deadlines.
     * Does NOT copy ideas, votes, comments, or favorites.
     *
     * @return int|null New challenge ID on success, null on failure
     */
    public static function duplicateChallenge(int $challengeId, int $userId): ?int
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can duplicate challenges');
            return null;
        }

        $tenantId = TenantContext::getId();

        $original = Database::query(
            "SELECT * FROM ideation_challenges WHERE id = ? AND tenant_id = ?",
            [$challengeId, $tenantId]
        )->fetch();

        if (!$original) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Challenge not found');
            return null;
        }

        $newTitle = '[Copy] ' . ($original['title'] ?? 'Untitled');

        try {
            Database::query(
                "INSERT INTO ideation_challenges
                    (tenant_id, user_id, title, description, category, category_id, tags, cover_image, prize_description, max_ideas_per_user, evaluation_criteria, status, ideas_count, favorites_count, views_count, is_featured, submission_deadline, voting_deadline, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 0, 0, 0, 0, NULL, NULL, NOW())",
                [
                    $tenantId,
                    $userId,
                    $newTitle,
                    $original['description'] ?? '',
                    $original['category'] ?? null,
                    $original['category_id'] ?? null,
                    $original['tags'] ?? null,
                    $original['cover_image'] ?? null,
                    $original['prize_description'] ?? null,
                    $original['max_ideas_per_user'] ?? null,
                    $original['evaluation_criteria'] ?? null,
                ]
            );

            $newId = (int)Database::lastInsertId();

            // Copy tag links from original challenge
            $originalTags = ChallengeTagService::getTagsForChallenge($challengeId);
            if (!empty($originalTags)) {
                $tagIds = array_map(fn($t) => (int)$t['id'], $originalTags);
                ChallengeTagService::syncTagsForChallenge($newId, $tagIds);
            }

            return $newId;
        } catch (\Throwable $e) {
            error_log("Challenge duplication failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to duplicate challenge');
            return null;
        }
    }

    // ============================================
    // VIEW TRACKING
    // ============================================

    /**
     * Increment the view count for a challenge
     *
     * Fire-and-forget — does not fail on error.
     */
    public static function incrementViews(int $challengeId): void
    {
        try {
            $tenantId = TenantContext::getId();
            Database::query(
                "UPDATE ideation_challenges SET views_count = views_count + 1 WHERE id = ? AND tenant_id = ?",
                [$challengeId, $tenantId]
            );
        } catch (\Throwable $e) {
            // Silently ignore — view tracking is non-critical
            error_log("Challenge view increment failed: " . $e->getMessage());
        }
    }

    /**
     * Delete a comment (owner or admin)
     */
    public static function deleteComment(int $commentId, int $userId): bool
    {
        self::clearErrors();

        $comment = Database::query(
            "SELECT c.*, i.challenge_id, ic.tenant_id
             FROM challenge_idea_comments c
             LEFT JOIN challenge_ideas i ON c.idea_id = i.id
             LEFT JOIN ideation_challenges ic ON i.challenge_id = ic.id
             WHERE c.id = ?",
            [$commentId]
        )->fetch();

        if (!$comment) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Comment not found');
            return false;
        }

        // Tenant scope check
        $tenantId = TenantContext::getId();
        if ((int)($comment['tenant_id'] ?? 0) !== $tenantId) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Comment not found');
            return false;
        }

        $isOwner = (int)$comment['user_id'] === $userId;
        $isAdmin = self::isAdmin($userId);

        if (!$isOwner && !$isAdmin) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only delete your own comments');
            return false;
        }

        try {
            Database::beginTransaction();

            $ideaId = (int)$comment['idea_id'];

            Database::query("DELETE FROM challenge_idea_comments WHERE id = ?", [$commentId]);

            // Decrement comments_count
            Database::query(
                "UPDATE challenge_ideas SET comments_count = GREATEST(0, comments_count - 1) WHERE id = ?",
                [$ideaId]
            );

            Database::commit();
            return true;
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Comment deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete comment');
            return false;
        }
    }

    // ============================================
    // IDEA → GROUP CONVERSION
    // ============================================

    /**
     * Convert a shortlisted or winning idea into a Group
     *
     * Creates a new group from the idea's title and description,
     * then links it back via source_idea_id / source_challenge_id.
     *
     * @return array|null Group data on success, null on failure
     */
    public static function convertIdeaToGroup(int $ideaId, int $userId): ?array
    {
        self::clearErrors();

        // 1. Get the idea
        $idea = self::getIdeaById($ideaId, $userId);

        if (!$idea) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Idea not found');
            return null;
        }

        // 2. Validate idea status
        $status = $idea['status'] ?? '';
        if (!in_array($status, ['shortlisted', 'winner'], true)) {
            self::addError(
                ApiErrorCodes::VALIDATION_INVALID_VALUE,
                'Only shortlisted or winning ideas can be converted to groups'
            );
            return null;
        }

        // 3. Check permissions: must be admin or idea creator
        $isAdmin = self::isAdmin($userId);
        $isCreator = (int)($idea['creator']['id'] ?? 0) === $userId;

        if (!$isAdmin && !$isCreator) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins or the idea creator can convert an idea to a group');
            return null;
        }

        // 4. Get challenge title for the group description
        $tenantId = TenantContext::getId();
        $challengeId = (int)($idea['challenge_id'] ?? 0);

        $challenge = Database::query(
            "SELECT title FROM ideation_challenges WHERE id = ? AND tenant_id = ?",
            [$challengeId, $tenantId]
        )->fetch();

        $challengeTitle = $challenge['title'] ?? 'Unknown Challenge';

        // 5. Create the group via GroupService
        $description = ($idea['description'] ?? '') . "\n\n---\nCreated from idea in challenge: {$challengeTitle}";

        $groupId = GroupService::create($userId, [
            'name' => $idea['title'] ?? 'Untitled Idea',
            'description' => $description,
            'visibility' => 'public',
        ]);

        if ($groupId === null) {
            // Propagate GroupService errors
            $groupErrors = GroupService::getErrors();
            foreach ($groupErrors as $err) {
                self::addError(
                    $err['code'] ?? ApiErrorCodes::SERVER_INTERNAL_ERROR,
                    $err['message'] ?? 'Failed to create group'
                );
            }
            return null;
        }

        // 6. Link the group back to the source idea and challenge
        try {
            Database::query(
                "UPDATE `groups` SET source_idea_id = ?, source_challenge_id = ? WHERE id = ? AND tenant_id = ?",
                [$ideaId, $challengeId, $groupId, $tenantId]
            );
        } catch (\Throwable $e) {
            error_log("Failed to set source columns on group {$groupId}: " . $e->getMessage());
            // Non-fatal — the group was already created successfully
        }

        // 7. Return the full group data
        return GroupService::getById($groupId, $userId);
    }
}
