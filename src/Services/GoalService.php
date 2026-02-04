<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * GoalService - Business logic for goals
 *
 * Provides methods for goal CRUD operations with standardized error handling.
 * Used by both v1 and v2 API controllers.
 *
 * @package Nexus\Services
 */
class GoalService
{
    /** @var array Collected errors */
    private static array $errors = [];

    /**
     * Get all validation errors
     *
     * @return array
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
     * Get goals with filtering and cursor-based pagination
     *
     * @param array $filters Optional filters: user_id, status, visibility, cursor, limit
     * @return array ['items' => [...], 'cursor' => ?string, 'has_more' => bool]
     */
    public static function getAll(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;
        $userId = $filters['user_id'] ?? null;
        $status = $filters['status'] ?? null; // 'active', 'completed', 'all'
        $visibility = $filters['visibility'] ?? null; // 'public', 'private', 'all'

        $params = [$tenantId];
        $where = ["g.tenant_id = ?"];

        // User filter - if not specified, get all visible goals
        if ($userId) {
            $where[] = "g.user_id = ?";
            $params[] = $userId;
        }

        // Status filter
        if ($status === 'active') {
            $where[] = "g.status = 'active'";
        } elseif ($status === 'completed') {
            $where[] = "g.status = 'completed'";
        }

        // Visibility filter
        if ($visibility === 'public') {
            $where[] = "g.is_public = 1";
        } elseif ($visibility === 'private') {
            $where[] = "g.is_public = 0";
        }

        // Cursor pagination
        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "g.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1; // Fetch one extra to check for more

        $sql = "
            SELECT
                g.*,
                u.first_name as owner_first_name,
                u.last_name as owner_last_name,
                u.avatar_url as owner_avatar,
                m.first_name as mentor_first_name,
                m.last_name as mentor_last_name,
                m.avatar_url as mentor_avatar
            FROM goals g
            LEFT JOIN users u ON g.user_id = u.id
            LEFT JOIN users m ON g.mentor_id = m.id
            WHERE {$whereClause}
            ORDER BY g.created_at DESC, g.id DESC
            LIMIT ?
        ";

        $goals = Database::query($sql, $params)->fetchAll();

        $hasMore = count($goals) > $limit;
        if ($hasMore) {
            array_pop($goals); // Remove the extra item
        }

        $nextCursor = null;
        if ($hasMore && !empty($goals)) {
            $lastGoal = end($goals);
            $nextCursor = base64_encode((string)$lastGoal['id']);
        }

        // Enrich each goal
        foreach ($goals as &$goal) {
            $goal = self::enrichGoal($goal);
        }

        return [
            'items' => $goals,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get public goals available for buddy offers
     *
     * @param int $excludeUserId User ID to exclude (can't buddy own goals)
     * @param array $filters
     * @return array
     */
    public static function getPublicForBuddy(int $excludeUserId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;

        $params = [$tenantId, $excludeUserId];
        $where = [
            "g.tenant_id = ?",
            "g.user_id != ?",
            "g.is_public = 1",
            "g.mentor_id IS NULL",
            "g.status = 'active'"
        ];

        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "g.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $sql = "
            SELECT
                g.*,
                u.first_name as owner_first_name,
                u.last_name as owner_last_name,
                u.avatar_url as owner_avatar
            FROM goals g
            LEFT JOIN users u ON g.user_id = u.id
            WHERE {$whereClause}
            ORDER BY g.created_at DESC, g.id DESC
            LIMIT ?
        ";

        $goals = Database::query($sql, $params)->fetchAll();

        $hasMore = count($goals) > $limit;
        if ($hasMore) {
            array_pop($goals);
        }

        $nextCursor = null;
        if ($hasMore && !empty($goals)) {
            $lastGoal = end($goals);
            $nextCursor = base64_encode((string)$lastGoal['id']);
        }

        foreach ($goals as &$goal) {
            $goal = self::enrichGoal($goal);
        }

        return [
            'items' => $goals,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single goal by ID
     *
     * @param int $id
     * @return array|null
     */
    public static function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $sql = "
            SELECT
                g.*,
                u.first_name as owner_first_name,
                u.last_name as owner_last_name,
                u.avatar_url as owner_avatar,
                m.first_name as mentor_first_name,
                m.last_name as mentor_last_name,
                m.avatar_url as mentor_avatar
            FROM goals g
            LEFT JOIN users u ON g.user_id = u.id
            LEFT JOIN users m ON g.mentor_id = m.id
            WHERE g.id = ? AND g.tenant_id = ?
        ";

        $goal = Database::query($sql, [$id, $tenantId])->fetch();

        if (!$goal) {
            return null;
        }

        return self::enrichGoal($goal);
    }

    /**
     * Enrich a goal with computed fields
     *
     * @param array $goal
     * @return array
     */
    private static function enrichGoal(array $goal): array
    {
        // Calculate progress percentage
        $targetValue = (float)($goal['target_value'] ?? 0);
        $currentValue = (float)($goal['current_value'] ?? 0);

        $goal['progress_percentage'] = $targetValue > 0
            ? min(100, round(($currentValue / $targetValue) * 100, 1))
            : 0;

        // Format owner info
        $goal['owner'] = [
            'id' => $goal['user_id'],
            'name' => trim(($goal['owner_first_name'] ?? '') . ' ' . ($goal['owner_last_name'] ?? '')),
            'avatar_url' => $goal['owner_avatar'] ?? null,
        ];

        // Format mentor info
        if (!empty($goal['mentor_id'])) {
            $goal['mentor'] = [
                'id' => $goal['mentor_id'],
                'name' => trim(($goal['mentor_first_name'] ?? '') . ' ' . ($goal['mentor_last_name'] ?? '')),
                'avatar_url' => $goal['mentor_avatar'] ?? null,
            ];
        } else {
            $goal['mentor'] = null;
        }

        // Boolean conversion
        $goal['is_public'] = (bool)($goal['is_public'] ?? false);

        // Clean up redundant fields
        unset(
            $goal['owner_first_name'],
            $goal['owner_last_name'],
            $goal['owner_avatar'],
            $goal['mentor_first_name'],
            $goal['mentor_last_name'],
            $goal['mentor_avatar']
        );

        return $goal;
    }

    /**
     * Create a new goal
     *
     * @param int $userId
     * @param array $data
     * @return int|null Goal ID on success, null on failure
     */
    public static function create(int $userId, array $data): ?int
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $targetValue = (float)($data['target_value'] ?? 0);
        $deadline = $data['deadline'] ?? null;
        $isPublic = !empty($data['is_public']);

        // Validation
        if (empty($title)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Goal title is required', 'title');
        }

        if (strlen($title) > 255) {
            self::addError(ApiErrorCodes::VALIDATION_TOO_LONG, 'Title cannot exceed 255 characters', 'title');
        }

        if ($targetValue < 0) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Target value cannot be negative', 'target_value');
        }

        if (!empty(self::$errors)) {
            return null;
        }

        try {
            Database::query(
                "INSERT INTO goals (tenant_id, user_id, title, description, target_value, current_value, deadline, is_public, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?, 'active', NOW())",
                [$tenantId, $userId, $title, $description ?: null, $targetValue, $deadline, $isPublic ? 1 : 0]
            );

            $goalId = Database::lastInsertId();

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 5, 'Created a goal');
                }
            } catch (\Throwable $e) {
                // Gamification is optional
            }

            return (int)$goalId;
        } catch (\Throwable $e) {
            error_log("Goal creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create goal');
            return null;
        }
    }

    /**
     * Update an existing goal
     *
     * @param int $id
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        self::clearErrors();

        $goal = self::getById($id);

        if (!$goal) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Goal not found');
            return false;
        }

        // Check ownership
        if ((int)$goal['user_id'] !== $userId) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only edit your own goals');
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

        if (array_key_exists('description', $data)) {
            $updates[] = "description = ?";
            $params[] = trim($data['description']) ?: null;
        }

        if (isset($data['target_value'])) {
            $targetValue = (float)$data['target_value'];
            if ($targetValue < 0) {
                self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Target value cannot be negative', 'target_value');
                return false;
            }
            $updates[] = "target_value = ?";
            $params[] = $targetValue;
        }

        if (array_key_exists('deadline', $data)) {
            $updates[] = "deadline = ?";
            $params[] = $data['deadline'];
        }

        if (isset($data['is_public'])) {
            $updates[] = "is_public = ?";
            $params[] = !empty($data['is_public']) ? 1 : 0;
        }

        if (empty($updates)) {
            return true; // Nothing to update
        }

        $params[] = $id;

        try {
            Database::query(
                "UPDATE goals SET " . implode(', ', $updates) . " WHERE id = ?",
                $params
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Goal update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update goal');
            return false;
        }
    }

    /**
     * Update goal progress
     *
     * @param int $id
     * @param int $userId
     * @param float $increment Amount to add to current value (can be negative)
     * @return array|null Updated goal on success, null on failure
     */
    public static function updateProgress(int $id, int $userId, float $increment): ?array
    {
        self::clearErrors();

        $goal = self::getById($id);

        if (!$goal) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Goal not found');
            return null;
        }

        // Check ownership
        if ((int)$goal['user_id'] !== $userId) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only update your own goals');
            return null;
        }

        // Can't update completed goals
        if ($goal['status'] === 'completed') {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Cannot update progress on a completed goal');
            return null;
        }

        $newValue = max(0, (float)$goal['current_value'] + $increment);
        $targetValue = (float)$goal['target_value'];

        try {
            Database::query(
                "UPDATE goals SET current_value = ? WHERE id = ?",
                [$newValue, $id]
            );

            // Check if goal is now completed
            if ($targetValue > 0 && $newValue >= $targetValue) {
                Database::query(
                    "UPDATE goals SET status = 'completed', completed_at = NOW() WHERE id = ?",
                    [$id]
                );

                // Award gamification points for completion
                try {
                    if (class_exists('\Nexus\Models\Gamification')) {
                        \Nexus\Models\Gamification::awardPoints($userId, 10, 'Completed goal: ' . $goal['title']);
                    }
                } catch (\Throwable $e) {
                    // Gamification is optional
                }
            }

            return self::getById($id);
        } catch (\Throwable $e) {
            error_log("Goal progress update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update progress');
            return null;
        }
    }

    /**
     * Offer to be a buddy/mentor for a goal
     *
     * @param int $goalId
     * @param int $userId
     * @return bool
     */
    public static function offerBuddy(int $goalId, int $userId): bool
    {
        self::clearErrors();

        $goal = self::getById($goalId);

        if (!$goal) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Goal not found');
            return false;
        }

        // Can't buddy your own goal
        if ((int)$goal['user_id'] === $userId) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'You cannot be a buddy for your own goal');
            return false;
        }

        // Must be public
        if (!$goal['is_public']) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'This goal is private');
            return false;
        }

        // Check if already has a mentor
        if (!empty($goal['mentor'])) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'This goal already has a buddy');
            return false;
        }

        try {
            Database::query(
                "UPDATE goals SET mentor_id = ? WHERE id = ?",
                [$userId, $goalId]
            );

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 5, 'Became a goal buddy');
                }
            } catch (\Throwable $e) {
                // Gamification is optional
            }

            // Create notification for goal owner
            try {
                $tenantId = TenantContext::getId();
                Database::query(
                    "INSERT INTO notifications (tenant_id, user_id, type, title, message, link, created_at)
                     VALUES (?, ?, 'goal_buddy', 'New Goal Buddy!', 'Someone offered to be your goal buddy', ?, NOW())",
                    [$tenantId, $goal['user_id'], '/goals/' . $goalId]
                );
            } catch (\Throwable $e) {
                // Notifications are optional
            }

            return true;
        } catch (\Throwable $e) {
            error_log("Buddy offer failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to become buddy');
            return false;
        }
    }

    /**
     * Delete a goal
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public static function delete(int $id, int $userId): bool
    {
        self::clearErrors();

        $goal = self::getById($id);

        if (!$goal) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Goal not found');
            return false;
        }

        // Check ownership (or admin)
        if ((int)$goal['user_id'] !== $userId) {
            $user = Database::query(
                "SELECT role FROM users WHERE id = ?",
                [$userId]
            )->fetch();

            if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
                self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only delete your own goals');
                return false;
            }
        }

        try {
            Database::query("DELETE FROM goals WHERE id = ?", [$id]);
            return true;
        } catch (\Throwable $e) {
            error_log("Goal deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete goal');
            return false;
        }
    }
}
