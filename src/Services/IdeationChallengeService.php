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
        $user = Database::query(
            "SELECT role FROM users WHERE id = ?",
            [$userId]
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

        $params = [$tenantId];
        $where = ["c.tenant_id = ?"];

        // Status filter
        if ($status && in_array($status, ['draft', 'open', 'voting', 'closed'])) {
            $where[] = "c.status = ?";
            $params[] = $status;
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
            $item = self::formatChallenge($item);
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
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

        $challenge = self::formatChallenge($challenge);

        // Add user's idea count for this challenge
        if ($userId) {
            $userIdeaCount = Database::query(
                "SELECT COUNT(*) AS cnt FROM challenge_ideas WHERE challenge_id = ? AND user_id = ?",
                [$id, $userId]
            )->fetch();
            $challenge['user_idea_count'] = (int)($userIdeaCount['cnt'] ?? 0);
        }

        return $challenge;
    }

    /**
     * Format challenge with creator info
     */
    private static function formatChallenge(array $challenge): array
    {
        $challenge['creator'] = [
            'id' => (int)$challenge['user_id'],
            'name' => trim(($challenge['creator_first_name'] ?? '') . ' ' . ($challenge['creator_last_name'] ?? '')),
            'avatar_url' => $challenge['creator_avatar'] ?? null,
        ];

        $challenge['ideas_count'] = (int)($challenge['ideas_count'] ?? 0);

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

        try {
            Database::query(
                "INSERT INTO ideation_challenges
                    (tenant_id, user_id, title, description, category, status, submission_deadline, voting_deadline, prize_description, max_ideas_per_user, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $tenantId,
                    $userId,
                    $title,
                    $description,
                    $category,
                    $status,
                    $submissionDeadline,
                    $votingDeadline,
                    $prizeDescription,
                    $maxIdeasPerUser,
                ]
            );

            $challengeId = Database::lastInsertId();

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

        if (empty($updates)) {
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

        $validStatuses = ['draft', 'open', 'voting', 'closed'];
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
        $currentStatus = $challenge['status'];
        $validTransitions = [
            'draft' => ['open'],
            'open' => ['voting', 'closed'],
            'voting' => ['closed'],
            'closed' => ['open'], // Allow reopening
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
        $sql = "
            SELECT
                i.*,
                u.first_name AS creator_first_name,
                u.last_name AS creator_last_name,
                u.avatar_url AS creator_avatar,
                ic.tenant_id
            FROM challenge_ideas i
            LEFT JOIN users u ON i.user_id = u.id
            LEFT JOIN ideation_challenges ic ON i.challenge_id = ic.id
            WHERE i.id = ?
        ";

        $idea = Database::query($sql, [$id])->fetch();

        if (!$idea) {
            return null;
        }

        // Tenant scope check
        $tenantId = TenantContext::getId();
        if ((int)($idea['tenant_id'] ?? 0) !== $tenantId) {
            return null;
        }

        unset($idea['tenant_id']);

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

        if (empty($title)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title is required', 'title');
        }

        if (empty($description)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Description is required', 'description');
        }

        if (!empty(self::$errors)) {
            return null;
        }

        try {
            Database::beginTransaction();

            Database::query(
                "INSERT INTO challenge_ideas (challenge_id, user_id, title, description, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$challengeId, $userId, $title, $description]
            );

            $ideaId = Database::lastInsertId();

            // Increment ideas_count on the challenge
            $tenantId = TenantContext::getId();
            Database::query(
                "UPDATE ideation_challenges SET ideas_count = ideas_count + 1 WHERE id = ? AND tenant_id = ?",
                [$challengeId, $tenantId]
            );

            Database::commit();

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 5, 'Submitted an idea');
                }
            } catch (\Throwable $e) {
                // Gamification is optional
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

        $params[] = $id;

        try {
            Database::query(
                "UPDATE challenge_ideas SET " . implode(', ', $updates) . " WHERE id = ?",
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
            Database::query("DELETE FROM challenge_ideas WHERE id = ?", [$id]);

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

        try {
            Database::query(
                "UPDATE challenge_ideas SET status = ? WHERE id = ?",
                [$status, $ideaId]
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
}
