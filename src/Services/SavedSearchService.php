<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * SavedSearchService - Saved/favourite search queries
 *
 * Allows users to save their search queries and filters for quick re-use.
 * Optional: notify_on_new flag for future "new results" notifications.
 *
 * API:
 * - POST   /api/v2/search/saved       - Save a search
 * - GET    /api/v2/search/saved        - List saved searches
 * - DELETE /api/v2/search/saved/{id}   - Delete a saved search
 * - POST   /api/v2/search/saved/{id}/run - Re-run a saved search
 */
class SavedSearchService
{
    /**
     * Maximum saved searches per user per tenant
     */
    private const MAX_SAVED_SEARCHES = 25;

    /**
     * Validation errors
     */
    private static array $errors = [];

    /**
     * Get validation errors
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Save a search for a user.
     *
     * @param int $userId User ID
     * @param string $name Display name for the saved search
     * @param array $queryParams Search query and filters as associative array
     * @param bool $notifyOnNew Whether to notify when new results match
     * @return int|null New saved search ID or null on error
     */
    public static function save(int $userId, string $name, array $queryParams, bool $notifyOnNew = false): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        // Validate name
        $name = trim($name);
        if (empty($name)) {
            self::$errors[] = ['code' => 'REQUIRED', 'message' => 'Name is required', 'field' => 'name'];
            return null;
        }
        if (strlen($name) > 255) {
            self::$errors[] = ['code' => 'TOO_LONG', 'message' => 'Name must be 255 characters or less', 'field' => 'name'];
            return null;
        }

        // Validate query_params has at least a query
        if (empty($queryParams['q']) && empty($queryParams['type']) && empty($queryParams['skills'])) {
            self::$errors[] = ['code' => 'REQUIRED', 'message' => 'Search query or filters are required', 'field' => 'query_params'];
            return null;
        }

        // Check limit
        $count = Database::query(
            "SELECT COUNT(*) as cnt FROM saved_searches WHERE tenant_id = ? AND user_id = ?",
            [$tenantId, $userId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ((int)($count['cnt'] ?? 0) >= self::MAX_SAVED_SEARCHES) {
            self::$errors[] = [
                'code' => 'LIMIT_REACHED',
                'message' => 'Maximum of ' . self::MAX_SAVED_SEARCHES . ' saved searches reached',
                'field' => null,
            ];
            return null;
        }

        // Insert
        Database::query(
            "INSERT INTO saved_searches (tenant_id, user_id, name, query_params, notify_on_new, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$tenantId, $userId, $name, json_encode($queryParams), $notifyOnNew ? 1 : 0]
        );

        return (int)Database::lastInsertId();
    }

    /**
     * Get all saved searches for a user.
     *
     * @param int $userId User ID
     * @return array Saved searches
     */
    public static function getAll(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $rows = Database::query(
            "SELECT id, name, query_params, notify_on_new, last_run_at, last_result_count, created_at
             FROM saved_searches
             WHERE tenant_id = ? AND user_id = ?
             ORDER BY created_at DESC",
            [$tenantId, $userId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            $row['id'] = (int)$row['id'];
            $row['query_params'] = json_decode($row['query_params'], true) ?: [];
            $row['notify_on_new'] = (bool)$row['notify_on_new'];
            $row['last_result_count'] = $row['last_result_count'] !== null ? (int)$row['last_result_count'] : null;
            return $row;
        }, $rows);
    }

    /**
     * Get a single saved search by ID.
     *
     * @param int $id Saved search ID
     * @param int $userId User ID (for authorization)
     * @return array|null Saved search data or null
     */
    public static function getById(int $id, int $userId): ?array
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT id, name, query_params, notify_on_new, last_run_at, last_result_count, created_at
             FROM saved_searches
             WHERE id = ? AND tenant_id = ? AND user_id = ?",
            [$id, $tenantId, $userId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['id'] = (int)$row['id'];
        $row['query_params'] = json_decode($row['query_params'], true) ?: [];
        $row['notify_on_new'] = (bool)$row['notify_on_new'];
        $row['last_result_count'] = $row['last_result_count'] !== null ? (int)$row['last_result_count'] : null;

        return $row;
    }

    /**
     * Delete a saved search.
     *
     * @param int $id Saved search ID
     * @param int $userId User ID (for authorization)
     * @return bool Success
     */
    public static function delete(int $id, int $userId): bool
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "DELETE FROM saved_searches WHERE id = ? AND tenant_id = ? AND user_id = ?",
            [$id, $tenantId, $userId]
        );

        return $result->rowCount() > 0;
    }

    /**
     * Update last_run_at and last_result_count when a saved search is re-run.
     *
     * @param int $id Saved search ID
     * @param int $userId User ID
     * @param int $resultCount Number of results from the search
     */
    public static function markRun(int $id, int $userId, int $resultCount): void
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE saved_searches
             SET last_run_at = NOW(), last_result_count = ?
             WHERE id = ? AND tenant_id = ? AND user_id = ?",
            [$resultCount, $id, $tenantId, $userId]
        );
    }

    /**
     * Update the notify_on_new flag for a saved search.
     *
     * @param int $id Saved search ID
     * @param int $userId User ID
     * @param bool $notify Whether to enable notifications
     * @return bool Success
     */
    public static function setNotification(int $id, int $userId, bool $notify): bool
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "UPDATE saved_searches
             SET notify_on_new = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND user_id = ?",
            [$notify ? 1 : 0, $id, $tenantId, $userId]
        );

        return $result->rowCount() > 0;
    }
}
