<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Listing;
use Nexus\Models\ActivityLog;

/**
 * ListingService - Business logic for listings
 *
 * This service extracts business logic from the Listing model and ListingController
 * to be shared between HTML and API controllers.
 *
 * Key operations:
 * - CRUD operations with validation
 * - Search and filtering
 * - Geospatial queries
 * - Attribute and SDG management
 * - Federation visibility handling
 */
class ListingService
{
    /**
     * Validation error messages
     */
    private static array $errors = [];

    /**
     * Get all active listings with optional filtering
     *
     * @param array $filters Associative array of filters:
     *   - type: string|array ('offer', 'request', or both)
     *   - category_id: int (filter by category ID)
     *   - category_slug: string (filter by category slug, alternative to category_id)
     *   - search: string (search term)
     *   - cursor: string (base64-encoded ID for cursor pagination)
     *   - limit: int (default 20, max 100)
     *   - user_id: int (filter by owner)
     *   - include_deleted: bool (default false)
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getAll(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = min((int)($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $sql = "SELECT l.id, l.title, l.description, l.type, l.category_id, l.image_url,
                       l.location, l.latitude, l.longitude, l.status, l.federated_visibility,
                       l.created_at, l.updated_at, l.user_id,
                       CASE
                           WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                           THEN u.organization_name
                           ELSE CONCAT(u.first_name, ' ', u.last_name)
                       END as author_name,
                       u.avatar_url as author_avatar,
                       c.name as category_name, c.color as category_color
                FROM listings l
                JOIN users u ON l.user_id = u.id
                LEFT JOIN categories c ON l.category_id = c.id
                WHERE l.tenant_id = ?";

        $params = [$tenantId];

        // Status filter
        if (empty($filters['include_deleted'])) {
            $sql .= " AND (l.status IS NULL OR l.status = 'active')";
        }

        // Type filter
        if (!empty($filters['type'])) {
            $type = $filters['type'];
            if (is_array($type)) {
                $placeholders = str_repeat('?,', count($type) - 1) . '?';
                $sql .= " AND l.type IN ($placeholders)";
                $params = array_merge($params, $type);
            } else {
                $sql .= " AND l.type = ?";
                $params[] = $type;
            }
        }

        // Category filter (by ID or slug)
        if (!empty($filters['category_id'])) {
            $sql .= " AND l.category_id = ?";
            $params[] = (int)$filters['category_id'];
        } elseif (!empty($filters['category_slug'])) {
            $sql .= " AND c.slug = ?";
            $params[] = $filters['category_slug'];
        }

        // User filter
        if (!empty($filters['user_id'])) {
            $sql .= " AND l.user_id = ?";
            $params[] = (int)$filters['user_id'];
        }

        // Search filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $sql .= " AND (l.title LIKE ? OR l.description LIKE ? OR l.location LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Cursor-based pagination
        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $sql .= " AND l.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        // Order by ID descending (for consistent cursor pagination)
        $sql .= " ORDER BY l.id DESC LIMIT ?";
        $params[] = $limit + 1; // Fetch one extra to determine has_more

        $items = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        // Check if there are more items
        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items); // Remove the extra item
        }

        // Generate next cursor
        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single listing by ID
     *
     * @param int $id Listing ID
     * @param bool $includeDeleted Include soft-deleted listings
     * @return array|null Listing data or null if not found
     */
    public static function getById(int $id, bool $includeDeleted = false): ?array
    {
        $sql = "SELECT l.*,
                       CASE
                           WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                           THEN u.organization_name
                           ELSE CONCAT(u.first_name, ' ', u.last_name)
                       END as author_name,
                       u.avatar_url as author_avatar, u.email as author_email,
                       c.name as category_name, c.color as category_color
                FROM listings l
                JOIN users u ON l.user_id = u.id
                LEFT JOIN categories c ON l.category_id = c.id
                WHERE l.id = ?";

        if (!$includeDeleted) {
            $sql .= " AND (l.status IS NULL OR l.status != 'deleted')";
        }

        $listing = Database::query($sql, [$id])->fetch(\PDO::FETCH_ASSOC);

        if (!$listing) {
            return null;
        }

        // Add attributes
        $listing['attributes'] = self::getAttributes($id);

        // Add like count
        $likeCount = Database::query(
            "SELECT COUNT(*) as cnt FROM likes WHERE target_type = 'listing' AND target_id = ?",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);
        $listing['likes_count'] = (int)($likeCount['cnt'] ?? 0);

        // Add comment count
        $commentCount = Database::query(
            "SELECT COUNT(*) as cnt FROM comments WHERE target_type = 'listing' AND target_id = ?",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);
        $listing['comments_count'] = (int)($commentCount['cnt'] ?? 0);

        return $listing;
    }

    /**
     * Get attributes for a listing
     */
    public static function getAttributes(int $listingId): array
    {
        $sql = "SELECT a.id, a.name, a.slug, la.value
                FROM listing_attributes la
                JOIN attributes a ON la.attribute_id = a.id
                WHERE la.listing_id = ?";

        return Database::query($sql, [$listingId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Validate listing data
     *
     * @param array $data Listing data to validate
     * @param bool $isUpdate Whether this is an update (some fields optional)
     * @return bool True if valid, false if errors
     */
    public static function validate(array $data, bool $isUpdate = false): bool
    {
        self::$errors = [];

        // Title validation
        if (!$isUpdate || isset($data['title'])) {
            $title = trim($data['title'] ?? '');
            if (empty($title)) {
                self::$errors[] = ['code' => 'REQUIRED', 'message' => 'Title is required', 'field' => 'title'];
            } elseif (strlen($title) > 255) {
                self::$errors[] = ['code' => 'TOO_LONG', 'message' => 'Title must be 255 characters or less', 'field' => 'title'];
            }
        }

        // Description validation
        if (!$isUpdate || isset($data['description'])) {
            $description = trim($data['description'] ?? '');
            if (empty($description)) {
                self::$errors[] = ['code' => 'REQUIRED', 'message' => 'Description is required', 'field' => 'description'];
            }
        }

        // Type validation
        if (!$isUpdate || isset($data['type'])) {
            $type = $data['type'] ?? 'offer';
            if (!in_array($type, ['offer', 'request'])) {
                self::$errors[] = ['code' => 'INVALID', 'message' => 'Type must be "offer" or "request"', 'field' => 'type'];
            }
        }

        // Category validation (optional but must exist if provided)
        if (!empty($data['category_id'])) {
            $tenantId = TenantContext::getId();
            $category = Database::query(
                "SELECT id FROM categories WHERE id = ? AND tenant_id = ?",
                [(int)$data['category_id'], $tenantId]
            )->fetch();
            if (!$category) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Category not found', 'field' => 'category_id'];
            }
        }

        // Federated visibility validation
        if (!empty($data['federated_visibility'])) {
            if (!in_array($data['federated_visibility'], ['none', 'listed', 'bookable'])) {
                self::$errors[] = ['code' => 'INVALID', 'message' => 'Invalid federated visibility value', 'field' => 'federated_visibility'];
            }
        }

        return empty(self::$errors);
    }

    /**
     * Get validation errors from last validate() call
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Create a new listing
     *
     * @param int $userId User creating the listing
     * @param array $data Listing data
     * @return int|null New listing ID or null on failure
     */
    public static function create(int $userId, array $data): ?int
    {
        if (!self::validate($data)) {
            return null;
        }

        $tenantId = TenantContext::getId();

        // Get user's location if not provided
        $location = $data['location'] ?? null;
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        if (empty($location) || empty($latitude) || empty($longitude)) {
            $user = Database::query(
                "SELECT location, latitude, longitude FROM users WHERE id = ?",
                [$userId]
            )->fetch(\PDO::FETCH_ASSOC);

            if ($user) {
                $location = $location ?? $user['location'];
                $latitude = $latitude ?? $user['latitude'];
                $longitude = $longitude ?? $user['longitude'];
            }
        }

        // Handle federated visibility
        $federatedVisibility = 'none';
        if (!empty($data['federated_visibility']) && in_array($data['federated_visibility'], ['listed', 'bookable'])) {
            if (self::canUseFederation($userId)) {
                $federatedVisibility = $data['federated_visibility'];
            }
        }

        // Insert listing
        $sql = "INSERT INTO listings (
                    tenant_id, user_id, title, description, type, category_id,
                    image_url, location, latitude, longitude, federated_visibility, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";

        Database::query($sql, [
            $tenantId,
            $userId,
            trim($data['title']),
            trim($data['description']),
            $data['type'] ?? 'offer',
            $data['category_id'] ?? null,
            $data['image_url'] ?? null,
            $location,
            $latitude,
            $longitude,
            $federatedVisibility,
        ]);

        $listingId = Database::lastInsertId();

        // Handle attributes
        if (!empty($data['attributes']) && is_array($data['attributes'])) {
            self::saveAttributes($listingId, $data['attributes']);
        }

        // Handle SDGs
        if (!empty($data['sdg_goals'])) {
            $sdgJson = is_array($data['sdg_goals']) ? json_encode($data['sdg_goals']) : $data['sdg_goals'];
            Database::query("UPDATE listings SET sdg_goals = ? WHERE id = ? AND tenant_id = ?", [$sdgJson, $listingId, TenantContext::getId()]);
        }

        // Log activity
        $type = $data['type'] ?? 'offer';
        ActivityLog::log($userId, "created_listing", "Posted a new $type: " . $data['title']);

        // Invalidate smart matching cache
        try {
            if (class_exists('\Nexus\Services\SmartMatchingEngine')) {
                \Nexus\Services\SmartMatchingEngine::invalidateCacheForCategory($data['category_id'] ?? null);
            }
        } catch (\Exception $e) {
            // Non-critical
        }

        // Check gamification badges
        try {
            if (class_exists('\Nexus\Services\GamificationService')) {
                \Nexus\Services\GamificationService::checkListingBadges($userId);
            }
        } catch (\Exception $e) {
            // Non-critical
        }

        return $listingId;
    }

    /**
     * Update an existing listing
     *
     * @param int $id Listing ID
     * @param int $userId User making the update
     * @param array $data Updated data
     * @return bool Success
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        $listing = self::getById($id);

        if (!$listing) {
            self::$errors = [['code' => 'NOT_FOUND', 'message' => 'Listing not found']];
            return false;
        }

        // Authorization check
        if (!self::canModify($listing, $userId)) {
            self::$errors = [['code' => 'FORBIDDEN', 'message' => 'You do not have permission to update this listing']];
            return false;
        }

        if (!self::validate($data, true)) {
            return false;
        }

        $tenantId = TenantContext::getId();

        // Build dynamic update
        $fields = [];
        $params = [];

        if (isset($data['title'])) {
            $fields[] = 'title = ?';
            $params[] = trim($data['title']);
        }

        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $params[] = trim($data['description']);
        }

        if (isset($data['type'])) {
            $fields[] = 'type = ?';
            $params[] = $data['type'];
        }

        if (array_key_exists('category_id', $data)) {
            $fields[] = 'category_id = ?';
            $params[] = $data['category_id'];
        }

        if (isset($data['image_url'])) {
            $fields[] = 'image_url = ?';
            $params[] = $data['image_url'];
        }

        if (isset($data['location'])) {
            $fields[] = 'location = ?';
            $params[] = $data['location'];
        }

        if (isset($data['latitude'])) {
            $fields[] = 'latitude = ?';
            $params[] = $data['latitude'];
        }

        if (isset($data['longitude'])) {
            $fields[] = 'longitude = ?';
            $params[] = $data['longitude'];
        }

        if (isset($data['federated_visibility'])) {
            $visibility = $data['federated_visibility'];
            if ($visibility === 'none' || self::canUseFederation($listing['user_id'])) {
                $fields[] = 'federated_visibility = ?';
                $params[] = $visibility;
            }
        }

        if (empty($fields)) {
            return true; // Nothing to update
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;

        $sql = "UPDATE listings SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?";
        Database::query($sql, $params);

        // Handle attributes if provided
        if (isset($data['attributes'])) {
            Database::query("DELETE FROM listing_attributes WHERE listing_id = ?", [$id]);
            if (is_array($data['attributes'])) {
                self::saveAttributes($id, $data['attributes']);
            }
        }

        // Handle SDGs if provided
        if (isset($data['sdg_goals'])) {
            $sdgJson = is_array($data['sdg_goals']) ? json_encode($data['sdg_goals']) : $data['sdg_goals'];
            Database::query("UPDATE listings SET sdg_goals = ? WHERE id = ? AND tenant_id = ?", [$sdgJson, $id, $tenantId]);
        }

        return true;
    }

    /**
     * Delete a listing (soft delete)
     *
     * @param int $id Listing ID
     * @param int $userId User making the deletion
     * @return bool Success
     */
    public static function delete(int $id, int $userId): bool
    {
        $listing = self::getById($id, true);

        if (!$listing) {
            self::$errors = [['code' => 'NOT_FOUND', 'message' => 'Listing not found']];
            return false;
        }

        // Authorization check
        if (!self::canModify($listing, $userId)) {
            self::$errors = [['code' => 'FORBIDDEN', 'message' => 'You do not have permission to delete this listing']];
            return false;
        }

        $tenantId = TenantContext::getId();

        // Soft delete
        Database::query(
            "UPDATE listings SET status = 'deleted', updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        return true;
    }

    /**
     * Check if a user can modify a listing (owner or admin)
     */
    public static function canModify(array $listing, int $userId): bool
    {
        // Owner can always modify
        if ((int)$listing['user_id'] === $userId) {
            return true;
        }

        // Check if user is admin
        $user = Database::query(
            "SELECT role, is_super_admin FROM users WHERE id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['role'] === 'admin' || $user['is_super_admin']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a user can use federation for listings
     */
    public static function canUseFederation(int $userId): bool
    {
        // Check if tenant has federation enabled
        if (!class_exists('\Nexus\Services\FederationFeatureService') ||
            !\Nexus\Services\FederationFeatureService::isTenantFederationEnabled()) {
            return false;
        }

        // Check if user has opted in
        $settings = Database::query(
            "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        return $settings && $settings['federation_optin'];
    }

    /**
     * Save listing attributes
     */
    private static function saveAttributes(int $listingId, array $attributes): void
    {
        foreach ($attributes as $attrId => $value) {
            if ($value) {
                Database::query(
                    "INSERT INTO listing_attributes (listing_id, attribute_id, value) VALUES (?, ?, ?)",
                    [$listingId, (int)$attrId, is_bool($value) ? '1' : (string)$value]
                );
            }
        }
    }

    /**
     * Get nearby listings with distance calculation
     *
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @param array $filters Additional filters (type, category_id, limit, cursor)
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getNearby(float $lat, float $lon, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $radiusKm = (float)($filters['radius_km'] ?? 25);
        $limit = min((int)($filters['limit'] ?? 20), 100);

        // Use the existing model method which has the Haversine formula
        $items = Listing::getNearby(
            $lat,
            $lon,
            $radiusKm,
            $limit,
            $filters['type'] ?? null,
            $filters['category_id'] ?? null
        );

        // Note: getNearby doesn't support cursor pagination due to distance sorting
        // For nearby queries, we use offset-based pagination
        return [
            'items' => $items,
            'cursor' => null,
            'has_more' => count($items) >= $limit,
        ];
    }

    /**
     * Search listings
     *
     * @param string $query Search query
     * @param array $filters Additional filters
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function search(string $query, array $filters = []): array
    {
        $filters['search'] = $query;
        return self::getAll($filters);
    }
}
