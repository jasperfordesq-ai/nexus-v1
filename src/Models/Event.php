<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;

class Event
{
    public static function create($tenantId, $userId, $title, $description, $location, $start, $end, $groupId = null, $categoryId = null, $lat = null, $lon = null, $federatedVisibility = 'none')
    {
        // Validate federated_visibility value
        $validVisibilities = ['none', 'listed', 'joinable'];
        if (!in_array($federatedVisibility, $validVisibilities)) {
            $federatedVisibility = 'none';
        }

        $sql = "INSERT INTO events (tenant_id, user_id, title, description, location, start_time, end_time, group_id, category_id, latitude, longitude, federated_visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        Database::query($sql, [$tenantId, $userId, $title, $description, $location, $start, $end, $groupId, $categoryId, $lat, $lon, $federatedVisibility]);
        return Database::getConnection()->lastInsertId();
    }

    public static function upcoming($tenantId, $limit = 50, $categoryId = null, $dateFilter = null, $search = null)
    {
        // SECURITY: Cast to int to prevent SQL injection
        $limit = (int)$limit;

        // Get upcoming events, sorted by nearest date first
        $sql = "SELECT e.*, u.name as organizer_name, c.name as category_name, c.color as category_color
                FROM events e
                JOIN users u ON e.user_id = u.id
                LEFT JOIN `groups` g ON e.group_id = g.id
                LEFT JOIN categories c ON e.category_id = c.id
                WHERE e.tenant_id = ? 
                AND e.start_time >= NOW()
                AND (g.visibility IS NULL OR g.visibility = 'public')";

        $params = [$tenantId];

        if ($categoryId) {
            $sql .= " AND e.category_id = ?";
            $params[] = $categoryId;
        }

        if ($dateFilter === 'weekend') {
            // Simple logic: Next Friday to Sunday? Or just Saturday/Sunday?
            // tailored for "This Weekend" usually means upcoming Fri/Sat/Sun
            $sql .= " AND WEEKOFYEAR(e.start_time) = WEEKOFYEAR(NOW()) AND DAYOFWEEK(e.start_time) IN (1, 6, 7)";
        } elseif ($dateFilter === 'month') {
            $sql .= " AND MONTH(e.start_time) = MONTH(NOW()) AND YEAR(e.start_time) = YEAR(NOW())";
        }

        if ($search) {
            $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ? OR u.name LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY e.start_time ASC LIMIT $limit";

        return Database::query($sql, $params)->fetchAll();
    }

    public static function getRange($tenantId, $startDate, $endDate)
    {
        $sql = "SELECT e.*, c.color as category_color 
                FROM events e 
                LEFT JOIN categories c ON e.category_id = c.id
                WHERE e.tenant_id = ? 
                AND e.start_time BETWEEN ? AND ?
                ORDER BY e.start_time ASC";
        return Database::query($sql, [$tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59'])->fetchAll();
    }

    public static function getForGroup($groupId)
    {
        $sql = "SELECT e.*, u.name as organizer_name, u.avatar_url as organizer_avatar, c.name as category_name, c.color as category_color
                FROM events e
                JOIN users u ON e.user_id = u.id
                LEFT JOIN categories c ON e.category_id = c.id
                WHERE e.group_id = ?
                ORDER BY e.start_time ASC";
        return Database::query($sql, [$groupId])->fetchAll();
    }

    /**
     * Find an event by ID with tenant scoping for security
     * @param int $id Event ID
     * @param int|null $tenantId Optional tenant ID for cross-tenant isolation
     * @return array|false Event data or false if not found
     */
    public static function find($id, $tenantId = null)
    {
        // SECURITY: If tenant context is available, enforce tenant isolation
        if ($tenantId === null && class_exists('\Nexus\Core\TenantContext')) {
            $tenantId = \Nexus\Core\TenantContext::getId();
        }

        if ($tenantId !== null) {
            $sql = "SELECT e.*, u.name as organizer_name, u.avatar_url as organizer_avatar
                    FROM events e
                    JOIN users u ON e.user_id = u.id
                    WHERE e.id = ? AND e.tenant_id = ?";
            return Database::query($sql, [$id, $tenantId])->fetch();
        }

        // Fallback for contexts without tenant (e.g., super admin)
        $sql = "SELECT e.*, u.name as organizer_name, u.avatar_url as organizer_avatar
                FROM events e
                JOIN users u ON e.user_id = u.id
                WHERE e.id = ?";
        return Database::query($sql, [$id])->fetch();
    }

    public static function update($id, $title, $description, $location, $start, $end, $groupId = null, $categoryId = null, $lat = null, $lon = null, $federatedVisibility = null)
    {
        // Build dynamic update query
        $fields = ['title = ?', 'description = ?', 'location = ?', 'start_time = ?', 'end_time = ?', 'group_id = ?', 'category_id = ?', 'latitude = ?', 'longitude = ?'];
        $params = [$title, $description, $location, $start, $end, $groupId, $categoryId, $lat, $lon];

        if ($federatedVisibility !== null) {
            $validVisibilities = ['none', 'listed', 'joinable'];
            if (in_array($federatedVisibility, $validVisibilities)) {
                $fields[] = 'federated_visibility = ?';
                $params[] = $federatedVisibility;
            }
        }

        $params[] = $id;
        $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = ?";
        return Database::query($sql, $params);
    }

    /**
     * Delete an event with ownership verification
     * @param int $id Event ID
     * @param int|null $userId User ID of the person deleting (for ownership check)
     * @param int|null $tenantId Tenant ID for cross-tenant isolation
     * @return bool True if deleted, false if not authorized
     */
    public static function delete($id, $userId = null, $tenantId = null)
    {
        // SECURITY: Get tenant context if not provided
        if ($tenantId === null && class_exists('\Nexus\Core\TenantContext')) {
            $tenantId = \Nexus\Core\TenantContext::getId();
        }

        // Build secure delete query with ownership and tenant checks
        $params = [$id];
        $conditions = ['id = ?'];

        if ($userId !== null) {
            $conditions[] = 'user_id = ?';
            $params[] = $userId;
        }

        if ($tenantId !== null) {
            $conditions[] = 'tenant_id = ?';
            $params[] = $tenantId;
        }

        $sql = "DELETE FROM events WHERE " . implode(' AND ', $conditions);
        $stmt = Database::query($sql, $params);
        return $stmt->rowCount() > 0;
    }
    public static function getAttending($userId)
    {
        $sql = "SELECT e.*, er.status as rsvp_status, u.name as organizer_name
                FROM events e
                JOIN event_rsvps er ON e.id = er.event_id
                JOIN users u ON e.user_id = u.id
                WHERE er.user_id = ? AND er.status = 'going' AND e.start_time >= NOW()
                ORDER BY e.start_time ASC
                LIMIT 10";
        return Database::query($sql, [$userId])->fetchAll();
    }

    public static function getHosted($userId)
    {
        $sql = "SELECT e.*,
                (SELECT count(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as attending_count,
                (SELECT count(*) FROM event_rsvps WHERE event_id = e.id AND status = 'invited') as invited_count
                FROM events e
                WHERE e.user_id = ? AND e.start_time >= NOW()
                ORDER BY e.start_time ASC";
        return Database::query($sql, [$userId])->fetchAll();
    }

    /**
     * Get nearby upcoming events using Haversine formula
     * @param float $lat User's latitude
     * @param float $lon User's longitude
     * @param float $radiusKm Search radius in kilometers
     * @param int $limit Maximum results
     * @param int|null $categoryId Filter by category
     * @return array Events sorted by distance
     */
    public static function getNearby($lat, $lon, $radiusKm = 25, $limit = 50, $categoryId = null)
    {
        $tenantId = \Nexus\Core\TenantContext::getId();
        $limit = (int)$limit;
        $radiusKm = (float)$radiusKm;

        try {
            $sql = "
                SELECT * FROM (
                    SELECT e.id, e.tenant_id, e.user_id, e.title, e.description, e.location,
                        e.start_time, e.end_time, e.group_id, e.category_id,
                        e.latitude, e.longitude, e.created_at, e.updated_at,
                        u.first_name, u.last_name,
                        CASE
                            WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                            ELSE CONCAT(u.first_name, ' ', u.last_name)
                        END as organizer_name,
                        u.avatar_url as organizer_avatar,
                        c.name as category_name, c.color as category_color,
                        (
                            6371 * acos(
                                LEAST(1.0, GREATEST(-1.0,
                                    cos(radians(?)) * cos(radians(e.latitude)) * cos(radians(e.longitude) - radians(?)) +
                                    sin(radians(?)) * sin(radians(e.latitude))
                                ))
                            )
                        ) AS calculated_distance_km
                    FROM events e
                    JOIN users u ON e.user_id = u.id
                    LEFT JOIN categories c ON e.category_id = c.id
                    WHERE e.tenant_id = ?
                    AND e.start_time > NOW()
                    AND e.latitude IS NOT NULL
                    AND e.longitude IS NOT NULL
            ";

            $params = [$lat, $lon, $lat, $tenantId];

            if ($categoryId) {
                $sql .= " AND e.category_id = ?";
                $params[] = $categoryId;
            }

            $sql .= "
                ) AS events_with_distance
                WHERE calculated_distance_km <= ?
                ORDER BY calculated_distance_km ASC
                LIMIT $limit
            ";

            // Rename calculated_distance_km to distance_km for consistency
            $sql = "SELECT *, calculated_distance_km AS distance_km FROM ($sql) AS final_results";

            $params[] = $radiusKm;

            return Database::query($sql, $params)->fetchAll();
        } catch (\Exception $e) {
            error_log("Event::getNearby error: " . $e->getMessage());
            return [];
        }
    }
}
