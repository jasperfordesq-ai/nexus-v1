<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use PDO;

use Nexus\Core\TenantContext;

class User
{
    public static function create($firstName, $lastName, $email, $password, $location = null, $phone = null, $profileType = 'individual', $orgName = null)
    {
        $tenantId = TenantContext::getId();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (tenant_id, first_name, last_name, email, password_hash, location, phone, profile_type, organization_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        Database::query($sql, [$tenantId, $firstName, $lastName, $email, $hash, $location, $phone, $profileType, $orgName]);
    }

    public static function findByEmail($email)
    {
        $tenantId = TenantContext::getId();
        // Allow user if they belong to this tenant OR if they are a super admin (accessing any tenant)
        $sql = "SELECT * FROM users WHERE email = ? AND (tenant_id = ? OR is_super_admin = 1)";
        return Database::query($sql, [$email, $tenantId])->fetch();
    }

    public static function findGlobalByEmail($email)
    {
        // Global lookup (unscoped) for login redirection
        $sql = "SELECT * FROM users WHERE email = ?";
        return Database::query($sql, [$email])->fetch();
    }

    /**
     * Find user by username (tenant-scoped)
     * Used for wallet transfers to protect email privacy
     */
    public static function findByUsername($username)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT * FROM users WHERE username = ? AND tenant_id = ?";
        return Database::query($sql, [$username, $tenantId])->fetch();
    }

    /**
     * Search users for wallet autocomplete
     * Returns users matching query by name or username
     * Excludes current user and includes avatar for display
     */
    public static function searchForWallet($query, $excludeUserId = null, $limit = 10)
    {
        $tenantId = TenantContext::getId();
        $searchTerm = '%' . $query . '%';
        $limit = (int)$limit;

        $sql = "
            SELECT id, first_name, last_name, username, avatar_url,
            CASE
                WHEN profile_type = 'organisation' AND organization_name IS NOT NULL AND organization_name != '' THEN organization_name
                ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))
            END as display_name
            FROM users
            WHERE tenant_id = ?
            AND is_approved = 1
            AND (
                username LIKE ? OR
                first_name LIKE ? OR
                last_name LIKE ? OR
                CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?
            )
        ";

        $params = [$tenantId, $searchTerm, $searchTerm, $searchTerm, $searchTerm];

        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }

        $sql .= " ORDER BY
            CASE WHEN username LIKE ? THEN 0 ELSE 1 END,
            first_name ASC
            LIMIT $limit";

        $params[] = $query . '%'; // Prioritize username starts-with matches

        return Database::query($sql, $params)->fetchAll();
    }

    public static function findById($id, $enforceTenant = true)
    {
        $tenantId = TenantContext::getId();

        // Check if online status columns exist (cached per request)
        static $hasOnlineColumns = null;
        if ($hasOnlineColumns === null) {
            try {
                $checkCol = Database::query("SHOW COLUMNS FROM users LIKE 'last_active_at'");
                $hasOnlineColumns = $checkCol->rowCount() > 0;
            } catch (\Exception $e) {
                $hasOnlineColumns = false;
            }
        }

        $onlineColumns = $hasOnlineColumns ? ", last_active_at, last_login_at" : ", NULL as last_active_at, NULL as last_login_at";

        // Check if gamification columns exist
        static $hasGamificationColumns = null;
        if ($hasGamificationColumns === null) {
            try {
                $checkXp = Database::query("SHOW COLUMNS FROM users LIKE 'xp'");
                $hasGamificationColumns = $checkXp->rowCount() > 0;
            } catch (\Exception $e) {
                $hasGamificationColumns = false;
            }
        }
        $gamificationColumns = $hasGamificationColumns ? ", COALESCE(xp, 0) as xp, COALESCE(level, 1) as level" : ", 0 as xp, 1 as level";

        // Smart Name Logic: If Org & OrgName specific, use it. Else First Last.
        $sql = "
            SELECT id, first_name, last_name,
            CASE
                WHEN profile_type = 'organisation' AND organization_name IS NOT NULL AND organization_name != '' THEN organization_name
                ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))
            END as name,
            organization_name,
            email, role, profile_type, balance, bio, location, phone, avatar_url, created_at, tenant_id, is_approved,
            privacy_profile, privacy_search, privacy_contact, is_super_admin{$gamificationColumns}{$onlineColumns}
            FROM users WHERE id = ?";

        // Security: Add tenant isolation unless explicitly disabled or user is super_admin
        if ($enforceTenant && $tenantId) {
            // Allow access if: same tenant OR the target user is a super_admin OR current user is super_admin
            $isSuperAdmin = !empty($_SESSION['is_super_admin']);
            if (!$isSuperAdmin) {
                $sql .= " AND (tenant_id = ? OR is_super_admin = 1)";
                return Database::query($sql, [$id, $tenantId])->fetch();
            }
        }

        return Database::query($sql, [$id])->fetch();
    }

    public static function updateProfile($id, $firstName, $lastName, $bio, $location, $phone, $profileType = 'individual', $orgName = null)
    {
        $tenantId = TenantContext::getId();
        $sql = "UPDATE users SET first_name = ?, last_name = ?, bio = ?, location = ?, phone = ?, profile_type = ?, organization_name = ? WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$firstName, $lastName, $bio, $location, $phone, $profileType, $orgName, $id, $tenantId]);
    }

    public static function updateAvatar($id, $url)
    {
        $tenantId = TenantContext::getId();
        $sql = "UPDATE users SET avatar_url = ? WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$url, $id, $tenantId]);
    }

    public static function updatePrivacy($id, $profile, $search, $contact)
    {
        $tenantId = TenantContext::getId();
        $sql = "UPDATE users SET privacy_profile = ?, privacy_search = ?, privacy_contact = ? WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$profile, $search, $contact, $id, $tenantId]);
    }

    /**
     * Update Notification Preferences
     * Stores preferences as JSON in the notification_preferences column
     */
    public static function updateNotificationPreferences($id, $preferences)
    {
        try {
            $tenantId = TenantContext::getId();
            $preferencesJson = json_encode($preferences);
            $sql = "UPDATE users SET notification_preferences = ? WHERE id = ? AND tenant_id = ?";
            Database::query($sql, [$preferencesJson, $id, $tenantId]);
            return true;
        } catch (\Exception $e) {
            // Column may not exist - log error but don't crash
            error_log('[User::updateNotificationPreferences] Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Notification Preferences
     */
    public static function getNotificationPreferences($id)
    {
        // Default preferences
        $defaults = [
            'email_messages' => 1,
            'email_connections' => 1,
            'email_transactions' => 1,
            'email_reviews' => 1,
            'push_enabled' => 1,
            // Organization notification preferences
            'email_org_payments' => 1,
            'email_org_transfers' => 1,
            'email_org_membership' => 1,
            'email_org_admin' => 1
        ];

        try {
            $tenantId = TenantContext::getId();
            $sql = "SELECT notification_preferences FROM users WHERE id = ? AND tenant_id = ?";
            $result = Database::query($sql, [$id, $tenantId]);
            $row = $result->fetch(\PDO::FETCH_ASSOC);

            if ($row && !empty($row['notification_preferences'])) {
                return json_decode($row['notification_preferences'], true) ?: $defaults;
            }
        } catch (\Exception $e) {
            // Column may not exist yet - return defaults
            error_log('[User::getNotificationPreferences] Error: ' . $e->getMessage());
        }

        return $defaults;
    }

    /**
     * Dynamic Update Method
     * Allows partial updates without overwriting other fields.
     * Security: Only allows whitelisted fields to prevent mass assignment vulnerabilities
     */
    public static function update($id, $data)
    {
        $tenantId = TenantContext::getId();

        // Security: Whitelist of allowed fields to prevent mass assignment attacks
        $allowedFields = [
            'name',
            'first_name',
            'last_name',
            'bio',
            'location',
            'phone',
            'avatar_url',
            'profile_type',
            'organization_name',
            'latitude',
            'longitude',
            'county',
            'town',
            'notification_preferences',
            'privacy_settings',
            'onboarding_completed',
            'fcm_token'
        ];

        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            // Security: Only allow whitelisted fields
            if (!in_array($key, $allowedFields)) {
                error_log("User::update() blocked attempt to update non-whitelisted field: $key");
                continue;
            }
            $fields[] = "`$key` = ?";
            $params[] = $value;
        }

        if (empty($fields)) {
            return;
        }

        // Handle users with NULL tenant_id (super admins / legacy users)
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL)";
        $params[] = $id;
        $params[] = $tenantId;

        Database::query($sql, $params);
    }

    public static function getAll()
    {
        $tenantId = TenantContext::getId();
        $sql = "
            SELECT u.id, u.first_name, u.last_name,
            CASE
                WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
            END as name,
            u.organization_name,
            u.email, u.avatar_url, u.location, u.profile_type, u.role, u.created_at, u.is_approved, u.is_super_admin,
            u.last_active_at,
            COUNT(l.id) as listing_count
            FROM users u
            LEFT JOIN listings l ON u.id = l.user_id AND l.status = 'active'
            WHERE u.tenant_id = ?
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ";
        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function getPendingUsers()
    {
        $tenantId = TenantContext::getId();
        $sql = "
            SELECT u.id, u.first_name, u.last_name,
            CASE
                WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
            END as name,
            u.organization_name,
            u.email, u.avatar_url, u.location, u.profile_type, u.role, u.created_at, u.is_approved, u.is_super_admin,
            u.last_active_at,
            COUNT(l.id) as listing_count
            FROM users u
            LEFT JOIN listings l ON u.id = l.user_id AND l.status = 'active'
            WHERE u.tenant_id = ? AND u.is_approved = 0
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ";
        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function getApprovedUsers()
    {
        $tenantId = TenantContext::getId();
        $sql = "
            SELECT u.id, u.first_name, u.last_name,
            CASE
                WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
            END as name,
            u.organization_name,
            u.email, u.avatar_url, u.location, u.profile_type, u.role, u.created_at, u.is_approved, u.is_super_admin,
            u.last_active_at,
            COUNT(l.id) as listing_count
            FROM users u
            LEFT JOIN listings l ON u.id = l.user_id AND l.status = 'active'
            WHERE u.tenant_id = ? AND u.is_approved = 1
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ";
        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function getAdminUsers()
    {
        $tenantId = TenantContext::getId();
        $sql = "
            SELECT u.id, u.first_name, u.last_name,
            CASE
                WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
            END as name,
            u.organization_name,
            u.email, u.avatar_url, u.location, u.profile_type, u.role, u.created_at, u.is_approved, u.is_super_admin,
            u.last_active_at,
            COUNT(l.id) as listing_count
            FROM users u
            LEFT JOIN listings l ON u.id = l.user_id AND l.status = 'active'
            WHERE u.tenant_id = ? AND (u.role IN ('admin', 'super_admin', 'tenant_admin') OR u.is_super_admin = 1)
            GROUP BY u.id
            ORDER BY u.is_super_admin DESC, u.created_at DESC
        ";
        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function getPaginated($limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();
        // Cast to int for safety and direct interpolation
        $limit = (int)$limit;
        $offset = (int)$offset;

        $sql = "
            SELECT u.id, u.tenant_id, u.first_name, u.last_name,
            CASE
                WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
            END as name,
            u.organization_name,
            u.email, u.avatar_url, u.location, u.role, u.created_at, u.is_approved,
            u.last_active_at,
            COUNT(l.id) as listing_count
            FROM users u
            LEFT JOIN listings l ON u.id = l.user_id AND l.status = 'active'
            WHERE u.tenant_id = ?
            GROUP BY u.id
            ORDER BY u.created_at DESC
            LIMIT $limit OFFSET $offset
        ";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function count()
    {
        $tenantId = TenantContext::getId();
        // Only count users with avatars (hidden from directory without avatar)
        $sql = "SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND avatar_url IS NOT NULL AND LENGTH(avatar_url) > 0";
        $result = Database::query($sql, [$tenantId])->fetch()['total'];
        error_log("User::count() for tenant {$tenantId}: {$result}");
        return $result;
    }

    public static function findPending()
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT *, first_name as name FROM users WHERE is_approved = 0 AND tenant_id = ? ORDER BY created_at ASC";
        return Database::query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Update user's last_active_at timestamp
     * Called on each request to track real-time online status
     *
     * @param int $userId User ID
     * @return bool Success status
     */
    public static function updateLastActive(int $userId): bool
    {
        try {
            $sql = "UPDATE users SET last_active_at = NOW() WHERE id = ?";
            Database::query($sql, [$userId]);
            return true;
        } catch (\Exception $e) {
            // Column may not exist yet - silently fail
            return false;
        }
    }

    /**
     * Check if a user is currently online
     * Online = last_active_at within the last 5 minutes
     *
     * @param int|null $userId User ID (null returns false)
     * @param string|null $lastActiveAt Pre-fetched last_active_at value
     * @return bool True if user is online
     */
    public static function isOnline(?int $userId = null, ?string $lastActiveAt = null): bool
    {
        if (!$userId && !$lastActiveAt) {
            return false;
        }

        // If we have a pre-fetched timestamp, use it
        if ($lastActiveAt !== null) {
            $lastActive = strtotime($lastActiveAt);
            return $lastActive && (time() - $lastActive) <= 300; // 5 minutes
        }

        // Otherwise query the database
        try {
            $sql = "SELECT last_active_at FROM users WHERE id = ?";
            $result = Database::query($sql, [$userId])->fetch(\PDO::FETCH_ASSOC);
            if ($result && !empty($result['last_active_at'])) {
                $lastActive = strtotime($result['last_active_at']);
                return $lastActive && (time() - $lastActive) <= 300; // 5 minutes
            }
        } catch (\Exception $e) {
            // Column may not exist yet
        }

        return false;
    }

    /**
     * Get online status text for display
     *
     * @param string|null $lastActiveAt Last active timestamp
     * @return string Status text (e.g., "Active now", "Active 2h ago")
     */
    public static function getOnlineStatusText(?string $lastActiveAt): string
    {
        if (!$lastActiveAt) {
            return 'Offline';
        }

        $lastActive = strtotime($lastActiveAt);
        if (!$lastActive) {
            return 'Offline';
        }

        $diff = time() - $lastActive;

        if ($diff <= 300) { // 5 minutes
            return 'Active now';
        } elseif ($diff <= 3600) { // 1 hour
            $mins = floor($diff / 60);
            return "Active {$mins}m ago";
        } elseif ($diff <= 86400) { // 24 hours
            $hours = floor($diff / 3600);
            return "Active {$hours}h ago";
        } elseif ($diff <= 604800) { // 7 days
            $days = floor($diff / 86400);
            return "Active {$days}d ago";
        } else {
            return 'Offline';
        }
    }

    public static function search($query)
    {
        $tenantId = TenantContext::getId();

        // Smart Search: Relevance Scoring
        $exactInfo = $query;
        $startsWith = $query . '%';
        $contains = '%' . $query . '%';

        $sql = "
            SELECT u.id, u.first_name, u.last_name,
            CASE
                WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                THEN u.organization_name
                ELSE TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))
            END as name,
            CASE
                WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                THEN u.organization_name
                ELSE TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))
            END as display_name,
            u.email, u.avatar_url, u.location, u.role, u.created_at,
            u.last_active_at,
            COUNT(l.id) as listing_count,
            (
                CASE
                    WHEN u.first_name = ? OR u.last_name = ? THEN 100 -- Exact Name
                    WHEN u.first_name LIKE ? OR u.last_name LIKE ? THEN 75 -- Starts With Name
                    WHEN u.first_name LIKE ? OR u.last_name LIKE ? THEN 50 -- Contains Name
                    WHEN u.location LIKE ? THEN 25 -- Location Match
                    ELSE 10 -- Other (Bio/Email)
                END
            ) as relevance_score
            FROM users u
            LEFT JOIN listings l ON u.id = l.user_id AND l.status = 'active'
            WHERE u.tenant_id = ?
            AND u.avatar_url IS NOT NULL
            AND LENGTH(u.avatar_url) > 0
            AND (
                u.first_name LIKE ? OR
                u.last_name LIKE ? OR
                u.email LIKE ? OR
                u.bio LIKE ? OR
                u.location LIKE ? OR
                u.phone LIKE ?
            )
            GROUP BY u.id
            ORDER BY relevance_score DESC, u.first_name ASC
            LIMIT 50
        ";

        // Params: 
        // Score Calculation: Exact(2), Starts(2), Contains(2), Location(1)
        // WHERE Clause: Tenant(1), Contains(6)
        $params = [
            $exactInfo,
            $exactInfo,
            $startsWith,
            $startsWith,
            $contains,
            $contains,
            $contains,
            $tenantId,
            $contains,
            $contains,
            $contains,
            $contains,
            $contains,
            $contains
        ];

        return Database::query($sql, $params)->fetchAll();
    }

    public static function approve($id)
    {
        $tenantId = TenantContext::getId();
        $sql = "UPDATE users SET is_approved = 1 WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$id, $tenantId]);
    }

    public static function getAdmins()
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT *, first_name as name FROM users WHERE role = 'admin' AND tenant_id = ?";
        return Database::query($sql, [$tenantId])->fetchAll();
    }

    public static function getSuperAdmins()
    {
        $sql = "SELECT * FROM users WHERE is_super_admin = 1";
        return Database::query($sql)->fetchAll();
    }
    public static function updateAdminFields($id, $role, $isApproved, $isSuperAdmin = null)
    {
        $tenantId = TenantContext::getId();

        // If super admin flag is provided, update it too
        if ($isSuperAdmin !== null) {
            $sql = "UPDATE users SET role = ?, is_approved = ?, is_super_admin = ? WHERE id = ? AND tenant_id = ?";
            Database::query($sql, [$role, $isApproved, $isSuperAdmin ? 1 : 0, $id, $tenantId]);
        } else {
            $sql = "UPDATE users SET role = ?, is_approved = ? WHERE id = ? AND tenant_id = ?";
            Database::query($sql, [$role, $isApproved, $id, $tenantId]);
        }
    }

    public static function delete($id)
    {
        $tenantId = TenantContext::getId();
        $sql = "DELETE FROM users WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$id, $tenantId]);
    }
    // Password Management
    public static function verifyPassword($id, $password)
    {
        // Internal check: fetch hash
        // We need a specific query because `findById` might not return the hash for security
        $sql = "SELECT password_hash FROM users WHERE id = ?";
        $user = Database::query($sql, [$id])->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            return true;
        }
        return false;
    }

    public static function updatePassword($id, $newPassword)
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        Database::query($sql, [$hash, $id]);
    }
    public static function getAllGlobal()
    {
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.is_approved, u.created_at, u.tenant_id, t.name as tenant_name, t.slug as tenant_slug
            FROM users u
            LEFT JOIN tenants t ON u.tenant_id = t.id
            ORDER BY u.created_at DESC
        ";
        return Database::query($sql)->fetchAll();
    }

    /**
     * Get nearby users using Haversine formula
     * @param float $lat User's latitude
     * @param float $lon User's longitude
     * @param float $radiusKm Search radius in kilometers
     * @param int $limit Maximum results
     * @param int $excludeUserId User ID to exclude (current user)
     * @return array Users sorted by distance
     */
    public static function getNearby($lat, $lon, $radiusKm = 25, $limit = 50, $excludeUserId = null)
    {
        $tenantId = TenantContext::getId();
        $limit = (int)$limit;

        try {
            // Haversine formula for distance calculation in SQL
            // Earth radius: 6371 km
            $sql = "
                SELECT u.id, u.first_name, u.last_name,
                CASE
                    WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                    ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
                END as name,
                u.organization_name,
                u.email, u.avatar_url, u.location, u.profile_type, u.role, u.created_at, u.is_approved,
                u.last_active_at,
                u.latitude, u.longitude,
                COUNT(l.id) as listing_count,
                (
                    6371 * acos(
                        LEAST(1.0, GREATEST(-1.0,
                            cos(radians(?)) * cos(radians(u.latitude)) * cos(radians(u.longitude) - radians(?)) +
                            sin(radians(?)) * sin(radians(u.latitude))
                        ))
                    )
                ) AS distance_km
                FROM users u
                LEFT JOIN listings l ON u.id = l.user_id AND l.status = 'active'
                WHERE u.tenant_id = ?
                AND u.latitude IS NOT NULL
                AND u.longitude IS NOT NULL
                AND u.id != ?
                GROUP BY u.id
                HAVING distance_km <= ?
                ORDER BY distance_km ASC
                LIMIT $limit
            ";

            $params = [
                $lat,
                $lon,
                $lat,
                $tenantId,
                $excludeUserId ?? 0,
                $radiusKm
            ];

            return Database::query($sql, $params)->fetchAll();
        } catch (\Exception $e) {
            // Log error and return empty array (columns may not exist yet)
            error_log("getNearby error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update user's coordinates
     */
    public static function updateCoordinates($id, $lat, $lon)
    {
        try {
            $tenantId = TenantContext::getId();
            $sql = "UPDATE users SET latitude = ?, longitude = ? WHERE id = ? AND tenant_id = ?";
            Database::query($sql, [$lat, $lon, $id, $tenantId]);
        } catch (\Exception $e) {
            // Columns may not exist yet
            error_log("updateCoordinates error: " . $e->getMessage());
        }
    }

    /**
     * Get user's coordinates
     */
    public static function getCoordinates($id)
    {
        try {
            $sql = "SELECT latitude, longitude FROM users WHERE id = ?";
            return Database::query($sql, [$id])->fetch();
        } catch (\Exception $e) {
            // Columns may not exist yet
            error_log("getCoordinates error: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // HIERARCHICAL TENANCY - SCOPED ADMIN METHODS
    // =========================================================================

    /**
     * Check if user is a tenant super admin (can access Super Admin Panel)
     */
    public static function isTenantSuperAdmin($userId): bool
    {
        $user = Database::query(
            "SELECT is_tenant_super_admin, is_super_admin FROM users WHERE id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        return $user && ($user['is_tenant_super_admin'] || $user['is_super_admin']);
    }

    /**
     * Check if user is the Master super admin (tenant_id = 1 + super admin)
     */
    public static function isMasterSuperAdmin($userId): bool
    {
        $user = Database::query(
            "SELECT tenant_id, is_tenant_super_admin, is_super_admin FROM users WHERE id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        return $user
            && (int)$user['tenant_id'] === 1
            && ($user['is_tenant_super_admin'] || $user['is_super_admin']);
    }

    /**
     * Get user's tenant with hierarchy info
     */
    public static function getTenantWithHierarchy($userId): ?array
    {
        return Database::query("
            SELECT
                t.id,
                t.name,
                t.slug,
                t.path,
                t.depth,
                t.parent_id,
                t.allows_subtenants,
                t.max_depth
            FROM users u
            JOIN tenants t ON u.tenant_id = t.id
            WHERE u.id = ?
        ", [$userId])->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create user with explicit tenant ID (for Super Admin Panel)
     */
    public static function createWithTenant(
        int $tenantId,
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        string $role = 'member',
        array $options = []
    ): ?int {
        // Check if email already exists
        $existing = Database::query("SELECT id FROM users WHERE email = ?", [$email])->fetch();
        if ($existing) {
            return null;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (
            tenant_id, first_name, last_name, name, email, password_hash, role,
            location, phone, profile_type, organization_name,
            is_approved, is_tenant_super_admin, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        Database::query($sql, [
            $tenantId,
            $firstName,
            $lastName,
            trim($firstName . ' ' . $lastName),
            $email,
            $hash,
            $role,
            $options['location'] ?? null,
            $options['phone'] ?? null,
            $options['profile_type'] ?? 'individual',
            $options['organization_name'] ?? null,
            $options['is_approved'] ?? 1,
            $options['is_tenant_super_admin'] ?? 0
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Move user to a different tenant
     */
    public static function moveTenant(int $userId, int $newTenantId): bool
    {
        try {
            Database::query(
                "UPDATE users SET tenant_id = ? WHERE id = ?",
                [$newTenantId, $userId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("User::moveTenant error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Grant tenant super admin privileges
     */
    public static function grantTenantSuperAdmin(int $userId): bool
    {
        try {
            Database::query(
                "UPDATE users SET is_tenant_super_admin = 1 WHERE id = ?",
                [$userId]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Revoke tenant super admin privileges
     */
    public static function revokeTenantSuperAdmin(int $userId): bool
    {
        try {
            Database::query(
                "UPDATE users SET is_tenant_super_admin = 0 WHERE id = ?",
                [$userId]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all tenant super admins for a specific tenant
     */
    public static function getTenantSuperAdmins(int $tenantId): array
    {
        return Database::query("
            SELECT id, first_name, last_name, email, role, created_at, last_login_at
            FROM users
            WHERE tenant_id = ?
            AND (is_tenant_super_admin = 1 OR is_super_admin = 1)
            ORDER BY last_name, first_name
        ", [$tenantId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if a user has god mode privileges
     * God users can grant/revoke super admin status from other users
     */
    public static function isGod(?int $userId = null): bool
    {
        if ($userId === null) {
            return !empty($_SESSION['is_god']);
        }

        $user = Database::query(
            "SELECT is_god FROM users WHERE id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        return $user && !empty($user['is_god']);
    }

    /**
     * Grant god mode to a user (can only be done by existing god)
     */
    public static function grantGodMode(int $userId): bool
    {
        if (!self::isGod()) {
            return false;
        }

        Database::query(
            "UPDATE users SET is_god = 1 WHERE id = ?",
            [$userId]
        );
        return true;
    }

    /**
     * Revoke god mode from a user (can only be done by existing god)
     */
    public static function revokeGodMode(int $userId): bool
    {
        if (!self::isGod()) {
            return false;
        }

        // Prevent revoking your own god mode
        if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
            return false;
        }

        Database::query(
            "UPDATE users SET is_god = 0 WHERE id = ?",
            [$userId]
        );
        return true;
    }
}
