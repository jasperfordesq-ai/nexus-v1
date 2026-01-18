<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\DatabaseWrapper; // Added Wrapper
use Nexus\Models\ActivityLog;

use Nexus\Core\TenantContext;

class Listing
{
    public static function all($type = null, $categoryId = null, $search = null)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT l.*,
                CASE
                    WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                    ELSE CONCAT(u.first_name, ' ', u.last_name)
                END as author_name,
                u.email as author_email, u.avatar_url, u.location as user_location, c.name as category_name
                FROM listings l
                JOIN users u ON l.user_id = u.id AND u.tenant_id = ?
                LEFT JOIN categories c ON l.category_id = c.id
                WHERE l.status = 'active' AND l.tenant_id = ?";

        // Start with tenant params for the JOIN and WHERE clauses
        $params = [$tenantId, $tenantId];

        if ($type) {
            $sql .= " AND l.type = ?";
            $params[] = $type;
        }
        if ($categoryId) {
            $sql .= " AND l.category_id = ?";
            $params[] = $categoryId;
        }
        if ($search) {
            // Simple search for legacy/standard calls
            $sql .= " AND (l.title LIKE ? OR l.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY l.created_at DESC";
        return Database::query($sql, $params)->fetchAll(); // Use Database directly with manual tenant filtering
    }

    public static function search($query)
    {
        $tenantId = TenantContext::getId();
        $term = '%' . $query . '%';
        $sql = "SELECT l.*,
                CASE
                    WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                    ELSE CONCAT(u.first_name, ' ', u.last_name)
                END as author_name,
                u.avatar_url, u.location as user_location, c.name as category_name
                FROM listings l
                JOIN users u ON l.user_id = u.id AND u.tenant_id = ?
                LEFT JOIN categories c ON l.category_id = c.id
                LEFT JOIN listing_attributes la ON l.id = la.listing_id
                LEFT JOIN attributes a ON la.attribute_id = a.id
                WHERE l.status = 'active' AND l.tenant_id = ?
                AND (
                    l.title LIKE ? OR
                    l.description LIKE ? OR
                    l.location LIKE ? OR
                    u.location LIKE ? OR
                    la.value LIKE ? OR
                    a.name LIKE ?
                )
                GROUP BY l.id
                ORDER BY l.created_at DESC
                LIMIT 50";

        return Database::query($sql, [$tenantId, $tenantId, $term, $term, $term, $term, $term, $term])->fetchAll();
    }

    public static function create($userId, $title, $description, $type = 'offer', $categoryId = null, $imageUrl = null, $location = null, $latitude = null, $longitude = null, $federatedVisibility = 'none')
    {
        $tenantId = \Nexus\Core\TenantContext::getId();

        // Validate federated_visibility value
        $validVisibilities = ['none', 'listed', 'bookable'];
        if (!in_array($federatedVisibility, $validVisibilities)) {
            $federatedVisibility = 'none';
        }

        $sql = "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, image_url, location, latitude, longitude, federated_visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        Database::query($sql, [$tenantId, $userId, $title, $description, $type, $categoryId, $imageUrl, $location, $latitude, $longitude, $federatedVisibility]);
        $id = Database::lastInsertId();

        ActivityLog::log($userId, "created_listing", "Posted a new $type: $title");

        return $id;
    }

    public static function getForUser($userId)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT * FROM listings WHERE user_id = ? AND tenant_id = ? AND (status IS NULL OR status != 'deleted') ORDER BY created_at DESC";
        return Database::query($sql, [$userId, $tenantId])->fetchAll();
    }

    public static function find($id)
    {
        // Removed manual tenant check
        $sql = "SELECT l.*, u.name as author_name, u.email as author_email, c.name as category_name
                FROM listings l
                JOIN users u ON l.user_id = u.id
                LEFT JOIN categories c ON l.category_id = c.id
                WHERE l.id = ? AND (l.status IS NULL OR l.status != 'deleted')";

        return DatabaseWrapper::query($sql, [$id], 'l')->fetch();
    }

    public static function getRecent($type, $limit = 5, $since = null)
    {
        $sql = "SELECT l.*, u.name as user_name 
                FROM listings l 
                JOIN users u ON l.user_id = u.id 
                WHERE l.type = ? AND l.status = 'active'";
        $params = [$type];

        if ($since) {
            $sql .= " AND l.created_at >= ?";
            $params[] = $since;
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT $limit";
        // FIXED DATA LEAK: Was using Database::query without tenant check.
        // Now uses Wrapper to enforce tenant isolation.
        return DatabaseWrapper::query($sql, $params, 'l')->fetchAll();
    }
    public static function countByUser($userId, $type)
    {
        // Match home page query pattern - filter by user and type, exclude deleted
        $sql = "SELECT COUNT(*) as count FROM listings WHERE user_id = ? AND type = ? AND (status IS NULL OR status != 'deleted')";
        $result = Database::query($sql, [$userId, $type])->fetch();
        return (int) ($result['count'] ?? 0);
    }

    public static function update($id, $title, $description, $type, $categoryId, $imageUrl, $location, $latitude, $longitude, $federatedVisibility = null)
    {
        $tenantId = \Nexus\Core\TenantContext::getId();

        // Build dynamic update query based on what's provided
        $fields = ['title = ?', 'description = ?', 'type = ?', 'category_id = ?', 'location = ?', 'latitude = ?', 'longitude = ?'];
        $params = [$title, $description, $type, $categoryId, $location, $latitude, $longitude];

        if ($imageUrl) {
            $fields[] = 'image_url = ?';
            $params[] = $imageUrl;
        }

        if ($federatedVisibility !== null) {
            $validVisibilities = ['none', 'listed', 'bookable'];
            if (in_array($federatedVisibility, $validVisibilities)) {
                $fields[] = 'federated_visibility = ?';
                $params[] = $federatedVisibility;
            }
        }

        $params[] = $id;
        $params[] = $tenantId;

        $sql = "UPDATE listings SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?";
        Database::query($sql, $params);
    }

    public static function delete($id)
    {
        $tenantId = \Nexus\Core\TenantContext::getId();
        // Secure Soft Delete
        $sql = "UPDATE listings SET status = 'deleted' WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$id, $tenantId]);
    }

    /**
     * Get nearby listings using Haversine formula
     * @param float $lat User's latitude
     * @param float $lon User's longitude
     * @param float $radiusKm Search radius in kilometers
     * @param int $limit Maximum results
     * @param string|null $type Filter by offer/request type
     * @param int|null $categoryId Filter by category
     * @return array Listings sorted by distance
     */
    public static function getNearby($lat, $lon, $radiusKm = 25, $limit = 50, $type = null, $categoryId = null)
    {
        $tenantId = TenantContext::getId();
        $limit = (int)$limit;
        $radiusKm = (float)$radiusKm;

        try {
            // Use subquery to filter by distance (avoids HAVING without GROUP BY issues)
            // Use explicit column list to avoid conflict if listings table has distance_km column
            $sql = "
                SELECT * FROM (
                    SELECT l.id, l.tenant_id, l.user_id, l.title, l.description, l.type, l.category_id,
                        l.image_url, l.location, l.latitude, l.longitude, l.status, l.created_at, l.updated_at,
                        u.first_name, u.last_name,
                        CASE
                            WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                            ELSE CONCAT(u.first_name, ' ', u.last_name)
                        END as author_name,
                        u.email as author_email, u.avatar_url, u.location as user_location,
                        c.name as category_name,
                        (
                            6371 * acos(
                                LEAST(1.0, GREATEST(-1.0,
                                    cos(radians(?)) * cos(radians(l.latitude)) * cos(radians(l.longitude) - radians(?)) +
                                    sin(radians(?)) * sin(radians(l.latitude))
                                ))
                            )
                        ) AS calculated_distance_km
                    FROM listings l
                    JOIN users u ON l.user_id = u.id AND u.tenant_id = ?
                    LEFT JOIN categories c ON l.category_id = c.id
                    WHERE l.tenant_id = ?
                    AND l.status = 'active'
                    AND l.latitude IS NOT NULL
                    AND l.longitude IS NOT NULL
            ";

            $params = [$lat, $lon, $lat, $tenantId, $tenantId];

            if ($type) {
                $sql .= " AND l.type = ?";
                $params[] = $type;
            }

            if ($categoryId) {
                $sql .= " AND l.category_id = ?";
                $params[] = $categoryId;
            }

            $sql .= "
                ) AS listings_with_distance
                WHERE calculated_distance_km <= ?
                ORDER BY calculated_distance_km ASC
                LIMIT $limit
            ";

            // Wrap in outer query to rename calculated_distance_km to distance_km for backward compatibility with views
            $sql = "SELECT *, calculated_distance_km AS distance_km FROM ($sql) AS final_results";

            $params[] = $radiusKm;

            return Database::query($sql, $params)->fetchAll();
        } catch (\Exception $e) {
            // Log error and return empty array (columns may not exist yet)
            error_log("Listing::getNearby error: " . $e->getMessage());
            return [];
        }
    }
}
