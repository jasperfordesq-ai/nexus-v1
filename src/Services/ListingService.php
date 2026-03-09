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
use Nexus\Services\ListingModerationService;
use Nexus\Services\ListingSkillTagService;
use Nexus\Services\SearchService;

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
     *   - current_user_id: int|null (when provided, adds is_favorited field)
     *   - skills: string|array (comma-separated or array of skill tags)
     *   - featured_first: bool (default false - sort featured to top)
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getAll(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = min((int)($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;
        $currentUserId = !empty($filters['current_user_id']) ? (int)$filters['current_user_id'] : null;

        if ($currentUserId !== null) {
            $sql = "SELECT l.id, l.title, l.description, l.type, l.category_id, l.image_url,
                           COALESCE(l.location, u.location) as location,
                           l.latitude, l.longitude, l.status, l.federated_visibility,
                           l.created_at, l.updated_at, l.user_id, l.hours_estimate,
                           CASE
                               WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                               THEN u.organization_name
                               ELSE CONCAT(u.first_name, ' ', u.last_name)
                           END as author_name,
                           u.avatar_url as author_avatar, u.tagline as tagline,
                           c.name as category_name, c.color as category_color,
                           CASE WHEN usl.id IS NOT NULL THEN 1 ELSE 0 END as is_favorited
                    FROM listings l
                    JOIN users u ON l.user_id = u.id
                    LEFT JOIN categories c ON l.category_id = c.id
                    LEFT JOIN user_saved_listings usl
                           ON usl.listing_id = l.id AND usl.user_id = ? AND usl.tenant_id = ?
                    WHERE l.tenant_id = ?";
            $params = [$currentUserId, $tenantId, $tenantId];
        } else {
            $sql = "SELECT l.id, l.title, l.description, l.type, l.category_id, l.image_url,
                           COALESCE(l.location, u.location) as location,
                           l.latitude, l.longitude, l.status, l.federated_visibility,
                           l.created_at, l.updated_at, l.user_id, l.hours_estimate,
                           CASE
                               WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                               THEN u.organization_name
                               ELSE CONCAT(u.first_name, ' ', u.last_name)
                           END as author_name,
                           u.avatar_url as author_avatar, u.tagline as tagline,
                           c.name as category_name, c.color as category_color
                    FROM listings l
                    JOIN users u ON l.user_id = u.id
                    LEFT JOIN categories c ON l.category_id = c.id
                    WHERE l.tenant_id = ?";
            $params = [$tenantId];
        }

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

        // Search filter — Meilisearch first, fall back to MySQL FULLTEXT
        if (!empty($filters['search'])) {
            $searchIds = SearchService::searchListings($filters['search'], $tenantId);
            if ($searchIds !== false && !empty($searchIds)) {
                // Meilisearch path: restrict to the ranked ID set
                $placeholders = implode(',', array_fill(0, count($searchIds), '?'));
                $sql .= " AND l.id IN ($placeholders)";
                $params = array_merge($params, array_map('intval', $searchIds));
                // Note: ORDER BY l.id DESC still applies — MatchRank or the
                // caller's ranking service re-orders by Meilisearch relevance.
            } elseif ($searchIds !== false) {
                // Meilisearch available but returned no hits
                $sql .= " AND 1=0";
            } else {
                // Meilisearch unavailable — fall back to MySQL FULLTEXT + LIKE
                $likeTerm = '%' . $filters['search'] . '%';
                $sql .= " AND (
                    MATCH(l.title, l.description) AGAINST(? IN BOOLEAN MODE)
                    OR l.location LIKE ?
                    OR u.first_name LIKE ?
                    OR u.last_name LIKE ?
                    OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE ?
                    OR u.organization_name LIKE ?
                )";
                $params[] = $filters['search']; // FULLTEXT — no % wrapping
                $params[] = $likeTerm;
                $params[] = $likeTerm;
                $params[] = $likeTerm;
                $params[] = $likeTerm;
                $params[] = $likeTerm;
            }
        }

        // Skills filter — filter by skill tags
        if (!empty($filters['skills'])) {
            $skills = is_array($filters['skills']) ? $filters['skills'] : explode(',', $filters['skills']);
            $skills = array_map('trim', $skills);
            $skills = array_filter($skills);

            if (!empty($skills)) {
                $skillPlaceholders = implode(',', array_fill(0, count($skills), '?'));
                $sql .= " AND l.id IN (
                    SELECT lst.listing_id FROM listing_skill_tags lst
                    WHERE lst.tenant_id = ? AND lst.tag IN ({$skillPlaceholders})
                )";
                $params[] = $tenantId;
                foreach ($skills as $skill) {
                    $params[] = strtolower(trim($skill));
                }
            }
        }

        // Cursor-based pagination
        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $sql .= " AND l.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        // Order: featured first (when requested), then by ID descending
        if (!empty($filters['featured_first'])) {
            $sql .= " ORDER BY l.is_featured DESC, l.id DESC LIMIT ?";
        } else {
            $sql .= " ORDER BY l.id DESC LIMIT ?";
        }
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

        // Build nested user object for each item (React expects listing.user)
        $items = array_map(function (array $item): array {
            $item['user'] = [
                'id'         => (int)($item['user_id'] ?? 0),
                'name'       => $item['author_name'] ?? null,
                'avatar'     => $item['author_avatar'] ?? null,
                'avatar_url' => $item['author_avatar'] ?? null,
                'tagline'    => $item['tagline'] ?? null,
            ];
            // Cast is_favorited to bool if present
            if (array_key_exists('is_favorited', $item)) {
                $item['is_favorited'] = (bool)$item['is_favorited'];
            }
            return $item;
        }, $items);

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
     * @param int|null $currentUserId If provided, attaches is_favorited field
     * @return array|null Listing data or null if not found
     */
    public static function getById(int $id, bool $includeDeleted = false, ?int $currentUserId = null): ?array
    {
        $favSelect = $currentUserId
            ? ', IF(usl.id IS NOT NULL, 1, 0) AS is_favorited'
            : ', 0 AS is_favorited';
        $favJoin = $currentUserId
            ? ' LEFT JOIN user_saved_listings usl ON usl.listing_id = l.id AND usl.user_id = ? AND usl.tenant_id = l.tenant_id'
            : '';

        $sql = "SELECT l.*,
                       COALESCE(l.location, u.location) as location,
                       CASE
                           WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                           THEN u.organization_name
                           ELSE CONCAT(u.first_name, ' ', u.last_name)
                       END as author_name,
                       u.avatar_url as author_avatar, u.email as author_email,
                       c.name as category_name, c.color as category_color
                       {$favSelect}
                FROM listings l
                JOIN users u ON l.user_id = u.id
                LEFT JOIN categories c ON l.category_id = c.id
                {$favJoin}
                WHERE l.id = ?";

        if (!$includeDeleted) {
            $sql .= " AND (l.status IS NULL OR l.status != 'deleted')";
        }

        $params = $currentUserId ? [$currentUserId, $id] : [$id];
        $listing = Database::query($sql, $params)->fetch(\PDO::FETCH_ASSOC);

        if (!$listing) {
            return null;
        }

        // Cast is_favorited to bool
        $listing['is_favorited'] = (bool)($listing['is_favorited'] ?? false);

        // Add attributes
        $listing['attributes'] = self::getAttributes($id);

        // Add hours_estimate (may not exist in all tenants — React uses conditional rendering)
        if (!array_key_exists('hours_estimate', $listing)) {
            $listing['hours_estimate'] = null;
        }

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

        // Add is_liked flag (for authenticated users)
        if ($currentUserId) {
            $isLiked = Database::query(
                "SELECT COUNT(*) as cnt FROM likes WHERE target_type = 'listing' AND target_id = ? AND user_id = ?",
                [$id, $currentUserId]
            )->fetch(\PDO::FETCH_ASSOC);
            $listing['is_liked'] = (int)($isLiked['cnt'] ?? 0) > 0;
        } else {
            $listing['is_liked'] = false;
        }

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

        // Check if moderation is enabled — new listings enter pending_review
        $initialStatus = 'active';
        $moderationStatus = null;
        try {
            $moderationStatus = ListingModerationService::getInitialModerationStatus();
            if ($moderationStatus === 'pending_review') {
                $initialStatus = 'pending';
            }
        } catch (\Exception $e) {
            // If moderation service unavailable, default to active
        }

        // Insert listing
        $sql = "INSERT INTO listings (
                    tenant_id, user_id, title, description, type, category_id,
                    image_url, location, latitude, longitude, federated_visibility, hours_estimate,
                    status, moderation_status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

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
            isset($data['hours_estimate']) ? floatval($data['hours_estimate']) : null,
            $initialStatus,
            $moderationStatus,
        ]);

        $listingId = Database::lastInsertId();

        // Record in feed_activity table — only for active listings (not pending moderation)
        if ($initialStatus === 'active') {
            try {
                FeedActivityService::recordActivity(TenantContext::getId(), $userId, 'listing', (int)$listingId, [
                    'title' => trim($data['title']),
                    'content' => trim($data['description']),
                    'image_url' => $data['image_url'] ?? null,
                    'metadata' => ['location' => $location],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $e) {
                error_log("ListingService::create feed_activity record failed: " . $e->getMessage());
            }
        }

        // Handle attributes
        if (!empty($data['attributes']) && is_array($data['attributes'])) {
            self::saveAttributes($listingId, $data['attributes']);
        }

        // Handle skill tags
        if (!empty($data['skill_tags']) && is_array($data['skill_tags'])) {
            try {
                ListingSkillTagService::setTags($listingId, $data['skill_tags']);
            } catch (\Exception $e) {
                // Non-critical — table may not exist yet
                error_log("ListingService::create skill_tags error: " . $e->getMessage());
            }
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

        // Send real-time match notifications to users with complementary listings
        try {
            if (class_exists('\Nexus\Services\MatchNotificationService')) {
                \Nexus\Services\MatchNotificationService::onListingCreated($listingId, $userId, $data);
            }
        } catch (\Exception $e) {
            // Non-critical — match notifications should never block listing creation
            error_log("MatchNotificationService error: " . $e->getMessage());
        }

        // Generate semantic embedding for matching and search boosting
        try {
            if (class_exists('\Nexus\Services\EmbeddingService')) {
                $embeddingRow = self::getById((int)$listingId);
                if ($embeddingRow) {
                    \Nexus\Services\EmbeddingService::generateForListing($embeddingRow);
                }
            }
        } catch (\Exception $e) {
            // Non-critical — embedding generation is async-safe
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

        if (array_key_exists('hours_estimate', $data)) {
            $fields[] = 'hours_estimate = ?';
            $params[] = isset($data['hours_estimate']) ? floatval($data['hours_estimate']) : null;
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
            Database::query("DELETE FROM listing_attributes WHERE listing_id = ? AND listing_id IN (SELECT id FROM listings WHERE tenant_id = ?)", [$id, $tenantId]);
            if (is_array($data['attributes'])) {
                self::saveAttributes($id, $data['attributes']);
            }
        }

        // Handle SDGs if provided
        if (isset($data['sdg_goals'])) {
            $sdgJson = is_array($data['sdg_goals']) ? json_encode($data['sdg_goals']) : $data['sdg_goals'];
            Database::query("UPDATE listings SET sdg_goals = ? WHERE id = ? AND tenant_id = ?", [$sdgJson, $id, $tenantId]);
        }

        // Refresh semantic embedding when content changes
        if (array_intersect(array_keys($data), ['title', 'description', 'skills'])) {
            try {
                if (class_exists('\Nexus\Services\EmbeddingService')) {
                    $embeddingRow = self::getById($id);
                    if ($embeddingRow) {
                        \Nexus\Services\EmbeddingService::generateForListing($embeddingRow);
                    }
                }
            } catch (\Exception $e) {
                // Non-critical
            }
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

        // Hide from feed_activity
        try {
            FeedActivityService::hideActivity('listing', $id);
        } catch (\Exception $e) {
            error_log("ListingService::delete feed_activity hide failed: " . $e->getMessage());
        }

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
            "SELECT role, is_super_admin, is_tenant_super_admin FROM users WHERE id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($user) {
            if (in_array($user['role'], ['admin', 'tenant_admin']) || $user['is_super_admin'] || $user['is_tenant_super_admin']) {
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

    // ============================================
    // SAVED / FAVOURITE LISTINGS
    // ============================================

    /**
     * Save a listing for the given user (idempotent).
     *
     * @param int $userId  The user saving the listing
     * @param int $listingId The listing to save
     * @return bool False if listing does not belong to the current tenant
     */
    public static function saveListing(int $userId, int $listingId): bool
    {
        $tenantId = TenantContext::getId();

        // Verify listing belongs to this tenant
        $listing = Database::query(
            "SELECT id FROM listings WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$listing) {
            return false;
        }

        // INSERT IGNORE for idempotency (UNIQUE KEY prevents duplicates)
        Database::query(
            "INSERT IGNORE INTO user_saved_listings (user_id, listing_id, tenant_id) VALUES (?, ?, ?)",
            [$userId, $listingId, $tenantId]
        );

        return true;
    }

    /**
     * Remove a saved listing for the given user.
     *
     * @param int $userId    The user unsaving the listing
     * @param int $listingId The listing to unsave
     * @return bool Always true (idempotent)
     */
    public static function unsaveListing(int $userId, int $listingId): bool
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "DELETE FROM user_saved_listings WHERE user_id = ? AND listing_id = ? AND tenant_id = ?",
            [$userId, $listingId, $tenantId]
        );

        return true;
    }

    /**
     * Get all listing IDs saved by the given user in the current tenant.
     *
     * @param int $userId The user whose saved listings to fetch
     * @return int[] Array of listing IDs
     */
    public static function getSavedListingIds(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $rows = Database::query(
            "SELECT listing_id FROM user_saved_listings WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map('intval', array_column($rows, 'listing_id'));
    }
}
