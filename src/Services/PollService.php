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
 * PollService - Business logic for polls
 *
 * Provides methods for poll CRUD operations with standardized error handling.
 * Used by both v1 and v2 API controllers.
 *
 * @package Nexus\Services
 */
class PollService
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
     * Get all polls with filtering and cursor-based pagination
     *
     * @param array $filters Optional filters: status, cursor, limit, user_id
     * @return array ['items' => [...], 'cursor' => ?string, 'has_more' => bool]
     */
    public static function getAll(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;
        $status = $filters['status'] ?? null; // 'open', 'closed', 'all'
        $userId = $filters['user_id'] ?? null;

        $params = [$tenantId];
        $where = ["p.tenant_id = ?"];

        // Status filter
        if ($status === 'open') {
            $where[] = "(p.expires_at IS NULL OR p.expires_at > NOW())";
        } elseif ($status === 'closed') {
            $where[] = "p.expires_at <= NOW()";
        }

        // User filter
        if ($userId) {
            $where[] = "p.user_id = ?";
            $params[] = $userId;
        }

        // Cursor pagination
        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "p.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1; // Fetch one extra to check for more

        $sql = "
            SELECT
                p.*,
                u.first_name as creator_first_name,
                u.last_name as creator_last_name,
                u.avatar_url as creator_avatar,
                (SELECT COUNT(*) FROM poll_votes v WHERE v.poll_id = p.id) as total_votes
            FROM polls p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE {$whereClause}
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT ?
        ";

        $polls = Database::query($sql, $params)->fetchAll();

        $hasMore = count($polls) > $limit;
        if ($hasMore) {
            array_pop($polls); // Remove the extra item
        }

        $nextCursor = null;
        if ($hasMore && !empty($polls)) {
            $lastPoll = end($polls);
            $nextCursor = base64_encode((string)$lastPoll['id']);
        }

        // Enrich each poll with options
        foreach ($polls as &$poll) {
            $poll = self::enrichPoll($poll);
        }

        return [
            'items' => $polls,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single poll by ID
     *
     * @param int $id
     * @param int|null $userId Current user ID for has_voted check
     * @return array|null
     */
    public static function getById(int $id, ?int $userId = null): ?array
    {
        $tenantId = TenantContext::getId();

        $sql = "
            SELECT
                p.*,
                u.first_name as creator_first_name,
                u.last_name as creator_last_name,
                u.avatar_url as creator_avatar,
                (SELECT COUNT(*) FROM poll_votes v WHERE v.poll_id = p.id) as total_votes
            FROM polls p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.id = ? AND p.tenant_id = ?
        ";

        $poll = Database::query($sql, [$id, $tenantId])->fetch();

        if (!$poll) {
            return null;
        }

        return self::enrichPoll($poll, $userId);
    }

    /**
     * Enrich a poll with options, percentages, and computed fields
     *
     * @param array $poll
     * @param int|null $userId
     * @return array
     */
    private static function enrichPoll(array $poll, ?int $userId = null): array
    {
        // Get options with vote counts
        $options = Database::query(
            "SELECT id, poll_id, label, votes FROM poll_options WHERE poll_id = ? ORDER BY id ASC",
            [$poll['id']]
        )->fetchAll();

        $totalVotes = (int)$poll['total_votes'];

        // Calculate percentages
        foreach ($options as &$option) {
            $option['vote_count'] = (int)$option['votes'];
            $option['percentage'] = $totalVotes > 0
                ? round(($option['votes'] / $totalVotes) * 100, 1)
                : 0;
            unset($option['votes']); // Remove raw votes, use vote_count instead
        }

        $poll['options'] = $options;
        $poll['total_votes'] = $totalVotes;

        // Compute status
        $expiresAt = $poll['expires_at'] ?? $poll['end_date'] ?? null;
        if ($expiresAt && strtotime($expiresAt) <= time()) {
            $poll['status'] = 'closed';
        } else {
            $poll['status'] = 'open';
        }

        // Check if user has voted
        if ($userId) {
            $hasVoted = Database::query(
                "SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?",
                [$poll['id'], $userId]
            )->fetch();
            $poll['has_voted'] = !empty($hasVoted);
            $poll['voted_option_id'] = $hasVoted ? (int)$hasVoted['option_id'] : null;
        }

        // Format creator info
        $poll['creator'] = [
            'id' => $poll['user_id'],
            'name' => trim(($poll['creator_first_name'] ?? '') . ' ' . ($poll['creator_last_name'] ?? '')),
            'avatar_url' => $poll['creator_avatar'] ?? null,
        ];

        // Clean up redundant fields
        unset($poll['creator_first_name'], $poll['creator_last_name'], $poll['creator_avatar']);

        return $poll;
    }

    /**
     * Create a new poll
     *
     * @param int $userId
     * @param array $data
     * @return int|null Poll ID on success, null on failure
     */
    public static function create(int $userId, array $data): ?int
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();
        $question = trim($data['question'] ?? '');
        $description = trim($data['description'] ?? '');
        $expiresAt = $data['expires_at'] ?? $data['end_date'] ?? null;
        $options = $data['options'] ?? [];

        // Validation
        if (empty($question)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Poll question is required', 'question');
        }

        if (empty($options) || !is_array($options) || count($options) < 2) {
            self::addError(ApiErrorCodes::VALIDATION_ERROR, 'At least 2 poll options are required', 'options');
        }

        if (count($options) > 10) {
            self::addError(ApiErrorCodes::VALIDATION_ERROR, 'Maximum 10 poll options allowed', 'options');
        }

        // Validate options are not empty
        foreach ($options as $i => $option) {
            if (empty(trim($option))) {
                self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, "Option " . ($i + 1) . " cannot be empty", "options.{$i}");
            }
        }

        if (!empty(self::$errors)) {
            return null;
        }

        try {
            Database::beginTransaction();

            // Insert poll
            Database::query(
                "INSERT INTO polls (tenant_id, user_id, question, description, expires_at, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                [$tenantId, $userId, $question, $description ?: null, $expiresAt]
            );

            $pollId = Database::lastInsertId();

            // Insert options
            foreach ($options as $label) {
                Database::query(
                    "INSERT INTO poll_options (poll_id, label, votes) VALUES (?, ?, 0)",
                    [$pollId, trim($label)]
                );
            }

            Database::commit();

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 5, 'Created a poll');
                }
            } catch (\Throwable $e) {
                // Gamification is optional
            }

            return (int)$pollId;
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Poll creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create poll');
            return null;
        }
    }

    /**
     * Update an existing poll
     *
     * @param int $id
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        self::clearErrors();

        $poll = self::getById($id);

        if (!$poll) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Poll not found');
            return false;
        }

        // Check ownership
        if ((int)$poll['user_id'] !== $userId) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only edit your own polls');
            return false;
        }

        // Can't edit closed polls
        if ($poll['status'] === 'closed') {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Cannot edit a closed poll');
            return false;
        }

        // Can't edit polls with votes (to maintain integrity)
        if ($poll['total_votes'] > 0) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Cannot edit a poll that has votes');
            return false;
        }

        $updates = [];
        $params = [];

        if (isset($data['question'])) {
            $question = trim($data['question']);
            if (empty($question)) {
                self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Question cannot be empty', 'question');
                return false;
            }
            $updates[] = "question = ?";
            $params[] = $question;
        }

        if (array_key_exists('description', $data)) {
            $updates[] = "description = ?";
            $params[] = trim($data['description']) ?: null;
        }

        if (array_key_exists('expires_at', $data)) {
            $updates[] = "expires_at = ?";
            $params[] = $data['expires_at'];
        }

        if (empty($updates)) {
            return true; // Nothing to update
        }

        $params[] = $id;

        try {
            Database::query(
                "UPDATE polls SET " . implode(', ', $updates) . " WHERE id = ?",
                $params
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Poll update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update poll');
            return false;
        }
    }

    /**
     * Delete a poll
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public static function delete(int $id, int $userId): bool
    {
        self::clearErrors();

        $poll = self::getById($id);

        if (!$poll) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Poll not found');
            return false;
        }

        // Check ownership (or admin)
        if ((int)$poll['user_id'] !== $userId) {
            // Check if user is admin
            $user = Database::query(
                "SELECT role FROM users WHERE id = ?",
                [$userId]
            )->fetch();

            if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
                self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only delete your own polls');
                return false;
            }
        }

        try {
            Database::beginTransaction();

            // Delete votes first (if no FK cascade)
            Database::query("DELETE FROM poll_votes WHERE poll_id = ?", [$id]);

            // Delete options
            Database::query("DELETE FROM poll_options WHERE poll_id = ?", [$id]);

            // Delete poll — scoped by tenant
            $tenantId = TenantContext::getId();
            Database::query("DELETE FROM polls WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

            Database::commit();
            return true;
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Poll deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete poll');
            return false;
        }
    }

    /**
     * Vote on a poll
     *
     * @param int $pollId
     * @param int $optionId
     * @param int $userId
     * @return bool
     */
    public static function vote(int $pollId, int $optionId, int $userId): bool
    {
        self::clearErrors();

        $poll = self::getById($pollId, $userId);

        if (!$poll) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Poll not found');
            return false;
        }

        // Check if poll is open
        if ($poll['status'] === 'closed') {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'This poll is closed');
            return false;
        }

        // Check if already voted
        if (!empty($poll['has_voted'])) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'You have already voted on this poll');
            return false;
        }

        // Validate option belongs to this poll
        $validOption = false;
        foreach ($poll['options'] as $option) {
            if ((int)$option['id'] === $optionId) {
                $validOption = true;
                break;
            }
        }

        if (!$validOption) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid poll option', 'option_id');
            return false;
        }

        try {
            Database::beginTransaction();

            // Record vote
            Database::query(
                "INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)",
                [$pollId, $optionId, $userId]
            );

            // Increment vote count on option
            Database::query(
                "UPDATE poll_options SET votes = votes + 1 WHERE id = ?",
                [$optionId]
            );

            Database::commit();

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 2, 'Voted in poll');
                }
            } catch (\Throwable $e) {
                // Gamification is optional
            }

            return true;
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Poll vote failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to record vote');
            return false;
        }
    }
}
