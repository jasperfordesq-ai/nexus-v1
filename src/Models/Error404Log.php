<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;

class Error404Log
{
    /**
     * Log a 404 error or increment hit count if URL already exists
     *
     * @param string $url The requested URL that returned 404
     * @param string|null $referer The HTTP referer
     * @param string|null $userAgent The user agent string
     * @param string|null $ipAddress The client IP address
     * @param int|null $userId The user ID if logged in
     * @return void
     */
    public static function log($url, $referer = null, $userAgent = null, $ipAddress = null, $userId = null)
    {
        $db = Database::getInstance();

        // Truncate long values to prevent errors
        $url = substr($url, 0, 1000);
        $referer = $referer ? substr($referer, 0, 1000) : null;
        $userAgent = $userAgent ? substr($userAgent, 0, 500) : null;
        $ipAddress = $ipAddress ? substr($ipAddress, 0, 45) : null;

        // Check if this URL already exists in the log
        $stmt = $db->prepare("SELECT id, hit_count FROM error_404_log WHERE url = ? LIMIT 1");
        $stmt->execute([$url]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing record
            $stmt = $db->prepare("
                UPDATE error_404_log
                SET hit_count = hit_count + 1,
                    last_seen_at = NOW(),
                    referer = COALESCE(?, referer),
                    user_agent = COALESCE(?, user_agent),
                    ip_address = COALESCE(?, ip_address),
                    user_id = COALESCE(?, user_id)
                WHERE id = ?
            ");
            $stmt->execute([$referer, $userAgent, $ipAddress, $userId, $existing['id']]);
        } else {
            // Insert new record
            $stmt = $db->prepare("
                INSERT INTO error_404_log
                (url, referer, user_agent, ip_address, user_id, first_seen_at, last_seen_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$url, $referer, $userAgent, $ipAddress, $userId]);
        }
    }

    /**
     * Get a single 404 error by ID
     *
     * @param int $id Error log ID
     * @return array|false
     */
    public static function getById($id)
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT
                id, url, referer, user_agent, ip_address, user_id,
                hit_count, first_seen_at, last_seen_at, resolved,
                redirect_id, notes
            FROM error_404_log
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        return $stmt->fetch();
    }

    /**
     * Get all 404 errors with pagination and filters
     *
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param string|null $orderBy Order column
     * @param string $orderDir Order direction (ASC/DESC)
     * @param bool|null $resolved Filter by resolved status
     * @return array
     */
    public static function getAll($page = 1, $perPage = 50, $orderBy = 'hit_count', $orderDir = 'DESC', $resolved = null)
    {
        $db = Database::getInstance();
        $perPage = (int)$perPage; // Ensure integer
        $offset = ((int)$page - 1) * $perPage;

        // Validate order by column
        $allowedColumns = ['url', 'hit_count', 'first_seen_at', 'last_seen_at', 'resolved'];
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'hit_count';
        }

        // Validate order direction
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        // Build query with optional filter
        $whereClause = '';
        $params = [];
        if ($resolved !== null) {
            $whereClause = 'WHERE resolved = ?';
            $params[] = $resolved ? 1 : 0;
        }

        // Get total count
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM error_404_log $whereClause");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        // Get paginated results - use direct integer values in query (safe after validation)
        // Cast to int to ensure no SQL injection and proper syntax
        $perPageInt = (int)$perPage;
        $offsetInt = (int)$offset;

        $sql = "
            SELECT
                id, url, referer, user_agent, ip_address, user_id,
                hit_count, first_seen_at, last_seen_at, resolved,
                redirect_id, notes
            FROM error_404_log
            $whereClause
            ORDER BY $orderBy $orderDir
            LIMIT $perPageInt OFFSET $offsetInt
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        return [
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get top 404 errors by hit count
     *
     * @param int $limit Number of results
     * @return array
     */
    public static function getTopErrors($limit = 20)
    {
        $db = Database::getInstance();
        $limit = (int)$limit; // Ensure integer

        $stmt = $db->prepare("
            SELECT
                id, url, referer, hit_count,
                first_seen_at, last_seen_at, resolved
            FROM error_404_log
            WHERE resolved = 0
            ORDER BY hit_count DESC
            LIMIT $limit
        ");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Mark a 404 error as resolved
     *
     * @param int $id Error log ID
     * @param int|null $redirectId Optional redirect ID that was created
     * @param string|null $notes Admin notes
     * @return bool
     */
    public static function markResolved($id, $redirectId = null, $notes = null)
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            UPDATE error_404_log
            SET resolved = 1,
                redirect_id = ?,
                notes = ?
            WHERE id = ?
        ");

        return $stmt->execute([$redirectId, $notes, $id]);
    }

    /**
     * Mark a 404 error as unresolved
     *
     * @param int $id Error log ID
     * @return bool
     */
    public static function markUnresolved($id)
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("UPDATE error_404_log SET resolved = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Delete a 404 error log entry
     *
     * @param int $id Error log ID
     * @return bool
     */
    public static function delete($id)
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("DELETE FROM error_404_log WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get 404 statistics
     *
     * @return array
     */
    public static function getStats()
    {
        $db = Database::getInstance();

        // Total unique 404s
        $stmt = $db->query("SELECT COUNT(*) as total FROM error_404_log");
        $total = $stmt->fetch()['total'];

        // Unresolved 404s
        $stmt = $db->query("SELECT COUNT(*) as unresolved FROM error_404_log WHERE resolved = 0");
        $unresolved = $stmt->fetch()['unresolved'];

        // Total hits
        $stmt = $db->query("SELECT SUM(hit_count) as total_hits FROM error_404_log");
        $totalHits = $stmt->fetch()['total_hits'] ?? 0;

        // Recent 404s (last 24 hours)
        $stmt = $db->query("
            SELECT COUNT(*) as recent
            FROM error_404_log
            WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $recent = $stmt->fetch()['recent'];

        return [
            'total' => $total,
            'unresolved' => $unresolved,
            'resolved' => $total - $unresolved,
            'total_hits' => $totalHits,
            'recent_24h' => $recent
        ];
    }

    /**
     * Search 404 errors by URL pattern
     *
     * @param string $search Search term
     * @param int $limit Result limit
     * @return array
     */
    public static function search($search, $limit = 50)
    {
        $db = Database::getInstance();
        $limit = (int)$limit; // Ensure integer

        $stmt = $db->prepare("
            SELECT
                id, url, referer, hit_count,
                first_seen_at, last_seen_at, resolved
            FROM error_404_log
            WHERE url LIKE ?
            ORDER BY hit_count DESC
            LIMIT $limit
        ");
        $stmt->execute(['%' . $search . '%']);

        return $stmt->fetchAll();
    }

    /**
     * Clean old resolved 404 errors
     *
     * @param int $daysOld Number of days old
     * @return int Number of deleted records
     */
    public static function cleanOldResolved($daysOld = 90)
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            DELETE FROM error_404_log
            WHERE resolved = 1
            AND last_seen_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$daysOld]);

        return $stmt->rowCount();
    }
}
