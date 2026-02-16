<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\User;

/**
 * UserService - Business logic for user profiles
 *
 * This service extracts business logic from the User model and ProfileController
 * to be shared between HTML and API controllers.
 *
 * Key operations:
 * - Profile retrieval (own and public)
 * - Profile updates with validation
 * - Privacy and notification preferences
 * - Avatar management
 */
class UserService
{
    /**
     * Validation error messages
     */
    private static array $errors = [];

    /**
     * Get the current user's full profile (for /me endpoint)
     *
     * @param int $userId
     * @return array|null
     */
    public static function getOwnProfile(int $userId): ?array
    {
        $user = User::findById($userId);

        if (!$user) {
            return null;
        }

        // Add additional profile data
        $profile = self::formatProfile($user, true);

        // Add notification preferences
        $profile['notification_preferences'] = User::getNotificationPreferences($userId);

        // Add statistics
        $profile['stats'] = self::getUserStats($userId);

        // Add badges if gamification is enabled
        $profile['badges'] = self::getUserBadges($userId);

        return $profile;
    }

    /**
     * Get a user's public profile
     *
     * @param int $userId User ID to view
     * @param int|null $viewerId ID of the user viewing (for connection status)
     * @return array|null
     */
    public static function getPublicProfile(int $userId, ?int $viewerId = null): ?array
    {
        $user = User::findById($userId);

        if (!$user) {
            return null;
        }

        // Check privacy settings
        $privacyLevel = $user['privacy_profile'] ?? 'public';

        if ($privacyLevel !== 'public' && $viewerId !== $userId) {
            // Check access based on privacy level
            if ($privacyLevel === 'members') {
                // Viewer must be logged in
                if (!$viewerId) {
                    self::$errors = [['code' => 'FORBIDDEN', 'message' => 'This profile is only visible to members']];
                    return null;
                }
            } elseif ($privacyLevel === 'connections') {
                // Viewer must be connected
                if (!$viewerId || !self::areConnected($userId, $viewerId)) {
                    self::$errors = [['code' => 'FORBIDDEN', 'message' => 'This profile is only visible to connections']];
                    return null;
                }
            }
        }

        // Format for public view (excludes sensitive data)
        $profile = self::formatProfile($user, false);

        // Add connection status if viewer is logged in
        if ($viewerId && $viewerId !== $userId) {
            $profile['connection_status'] = self::getConnectionStatus($userId, $viewerId);
        }

        // Add public stats
        $profile['stats'] = self::getPublicStats($userId);

        // Add badges
        $profile['badges'] = self::getUserBadges($userId);

        return $profile;
    }

    /**
     * Format user data for API response
     *
     * @param array $user Raw user data
     * @param bool $includePrivate Include private fields (email, phone, etc.)
     * @return array
     */
    private static function formatProfile(array $user, bool $includePrivate = false): array
    {
        $profile = [
            'id' => (int)$user['id'],
            'name' => $user['name'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
            'bio' => $user['bio'] ?? null,
            'location' => $user['location'] ?? null,
            'profile_type' => $user['profile_type'] ?? 'individual',
            'organization_name' => $user['organization_name'] ?? null,
            'created_at' => $user['created_at'] ?? null,
            'is_online' => User::isOnline(null, $user['last_active_at'] ?? null),
            'online_status' => User::getOnlineStatusText($user['last_active_at'] ?? null),
        ];

        // Gamification fields
        if (isset($user['xp'])) {
            $profile['xp'] = (int)$user['xp'];
        }
        if (isset($user['level'])) {
            $profile['level'] = (int)$user['level'];
        }

        // Private fields (only for own profile)
        if ($includePrivate) {
            $profile['email'] = $user['email'] ?? null;
            $profile['phone'] = $user['phone'] ?? null;
            $profile['balance'] = (float)($user['balance'] ?? 0);
            $profile['role'] = $user['role'] ?? 'member';
            $profile['is_admin'] = in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'super_admin']) || !empty($user['is_super_admin']) || !empty($user['is_tenant_super_admin']);
            $profile['is_super_admin'] = !empty($user['is_super_admin']);
            $profile['is_approved'] = (bool)($user['is_approved'] ?? false);
            $profile['privacy_profile'] = $user['privacy_profile'] ?? 'public';
            $profile['privacy_search'] = (bool)($user['privacy_search'] ?? true);
            $profile['privacy_contact'] = (bool)($user['privacy_contact'] ?? true);
            $profile['onboarding_completed'] = (bool)($user['onboarding_completed'] ?? false);
        }

        return $profile;
    }

    /**
     * Get user statistics
     */
    private static function getUserStats(int $userId): array
    {
        $tenantId = TenantContext::getId();

        // Count listings
        $listingCount = Database::query(
            "SELECT COUNT(*) as cnt FROM listings WHERE user_id = ? AND tenant_id = ? AND (status IS NULL OR status = 'active')",
            [$userId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        // Count transactions
        $transactionCount = Database::query(
            "SELECT COUNT(*) as cnt FROM transactions WHERE (sender_id = ? OR receiver_id = ?)",
            [$userId, $userId]
        )->fetch(\PDO::FETCH_ASSOC);

        // Count connections
        $connectionCount = Database::query(
            "SELECT COUNT(*) as cnt FROM connections WHERE (requester_id = ? OR receiver_id = ?) AND status = 'accepted'",
            [$userId, $userId]
        )->fetch(\PDO::FETCH_ASSOC);

        // Average review score
        $reviews = Database::query(
            "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE receiver_id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        return [
            'listings_count' => (int)($listingCount['cnt'] ?? 0),
            'transactions_count' => (int)($transactionCount['cnt'] ?? 0),
            'connections_count' => (int)($connectionCount['cnt'] ?? 0),
            'reviews_count' => (int)($reviews['review_count'] ?? 0),
            'average_rating' => $reviews['avg_rating'] ? round((float)$reviews['avg_rating'], 1) : null,
        ];
    }

    /**
     * Get public stats (subset of full stats)
     */
    private static function getPublicStats(int $userId): array
    {
        $stats = self::getUserStats($userId);

        // Remove private stats
        unset($stats['transactions_count']);

        return $stats;
    }

    /**
     * Get user badges
     */
    private static function getUserBadges(int $userId): array
    {
        try {
            $badges = Database::query(
                "SELECT b.name, b.badge_key, b.icon, b.description, ub.earned_at
                 FROM user_badges ub
                 JOIN badges b ON ub.badge_key = b.badge_key AND ub.tenant_id = b.tenant_id
                 WHERE ub.user_id = ?
                 ORDER BY ub.earned_at DESC",
                [$userId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            return $badges;
        } catch (\Exception $e) {
            // Badges table may not exist
            return [];
        }
    }

    /**
     * Validate profile update data
     *
     * @param array $data Data to validate
     * @return bool
     */
    public static function validateProfileUpdate(array $data): bool
    {
        self::$errors = [];

        // First name validation
        if (isset($data['first_name'])) {
            $firstName = trim($data['first_name']);
            if (strlen($firstName) > 100) {
                self::$errors[] = ['code' => 'TOO_LONG', 'message' => 'First name must be 100 characters or less', 'field' => 'first_name'];
            }
        }

        // Last name validation
        if (isset($data['last_name'])) {
            $lastName = trim($data['last_name']);
            if (strlen($lastName) > 100) {
                self::$errors[] = ['code' => 'TOO_LONG', 'message' => 'Last name must be 100 characters or less', 'field' => 'last_name'];
            }
        }

        // Bio validation (basic XSS protection done at save time)
        if (isset($data['bio'])) {
            if (strlen($data['bio']) > 5000) {
                self::$errors[] = ['code' => 'TOO_LONG', 'message' => 'Bio must be 5000 characters or less', 'field' => 'bio'];
            }
        }

        // Profile type validation
        if (isset($data['profile_type'])) {
            if (!in_array($data['profile_type'], ['individual', 'organisation'])) {
                self::$errors[] = ['code' => 'INVALID', 'message' => 'Profile type must be "individual" or "organisation"', 'field' => 'profile_type'];
            }
        }

        // Phone validation (basic format)
        if (isset($data['phone']) && !empty($data['phone'])) {
            $phone = preg_replace('/[^0-9+\-\s\(\)]/', '', $data['phone']);
            if (strlen($phone) < 6 || strlen($phone) > 20) {
                self::$errors[] = ['code' => 'INVALID', 'message' => 'Invalid phone number format', 'field' => 'phone'];
            }
        }

        return empty(self::$errors);
    }

    /**
     * Get validation errors
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Update user profile
     *
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public static function updateProfile(int $userId, array $data): bool
    {
        if (!self::validateProfileUpdate($data)) {
            return false;
        }

        $updateData = [];

        // Allowed profile fields
        $allowedFields = [
            'first_name', 'last_name', 'bio', 'location', 'phone',
            'profile_type', 'organization_name', 'latitude', 'longitude'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];

                // Sanitize bio HTML
                if ($field === 'bio' && $value !== null) {
                    $value = self::sanitizeBio($value);
                }

                $updateData[$field] = $value;
            }
        }

        if (empty($updateData)) {
            return true; // Nothing to update
        }

        User::update($userId, $updateData);

        return true;
    }

    /**
     * Sanitize bio HTML (allow safe tags only)
     */
    private static function sanitizeBio(string $bio): string
    {
        // Allow only safe HTML tags
        $allowedTags = '<p><br><strong><em><u><ul><ol><li><a>';
        $bio = strip_tags($bio, $allowedTags);

        // Sanitize href attributes in links to prevent javascript: URLs
        $bio = preg_replace_callback(
            '/<a\s+([^>]*)href\s*=\s*["\']([^"\']*)["\']([^>]*)>/i',
            function ($matches) {
                $href = $matches[2];
                // Only allow http, https, and mailto URLs
                if (!preg_match('/^(https?:|mailto:)/i', $href)) {
                    $href = '#';
                }
                return '<a ' . $matches[1] . 'href="' . htmlspecialchars($href) . '"' . $matches[3] . '>';
            },
            $bio
        );

        return $bio;
    }

    /**
     * Update privacy settings
     *
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public static function updatePrivacy(int $userId, array $data): bool
    {
        self::$errors = [];

        $profile = $data['privacy_profile'] ?? null;
        $search = $data['privacy_search'] ?? null;
        $contact = $data['privacy_contact'] ?? null;

        // Validate privacy_profile
        if ($profile !== null && !in_array($profile, ['public', 'members', 'connections'])) {
            self::$errors[] = ['code' => 'INVALID', 'message' => 'Invalid privacy profile value', 'field' => 'privacy_profile'];
            return false;
        }

        // Get current values if not provided
        if ($profile === null || $search === null || $contact === null) {
            $user = User::findById($userId);
            if (!$user) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
                return false;
            }
            $profile = $profile ?? $user['privacy_profile'] ?? 'public';
            $search = $search ?? $user['privacy_search'] ?? 1;
            $contact = $contact ?? $user['privacy_contact'] ?? 1;
        }

        User::updatePrivacy(
            $userId,
            $profile,
            $search ? 1 : 0,
            $contact ? 1 : 0
        );

        return true;
    }

    /**
     * Update notification preferences
     *
     * @param int $userId
     * @param array $preferences
     * @return bool
     */
    public static function updateNotificationPreferences(int $userId, array $preferences): bool
    {
        // Get current preferences
        $current = User::getNotificationPreferences($userId);

        // Merge with new values (only update provided keys)
        $validKeys = [
            'email_messages', 'email_connections', 'email_transactions', 'email_reviews',
            'push_enabled', 'email_org_payments', 'email_org_transfers',
            'email_org_membership', 'email_org_admin',
            'email_gamification_digest', 'email_gamification_milestones'
        ];

        foreach ($preferences as $key => $value) {
            if (in_array($key, $validKeys)) {
                $current[$key] = $value ? 1 : 0;
            }
        }

        return User::updateNotificationPreferences($userId, $current);
    }

    /**
     * Update user avatar
     *
     * @param int $userId
     * @param array $file $_FILES['avatar'] data
     * @return string|null New avatar URL or null on failure
     */
    public static function updateAvatar(int $userId, array $file): ?string
    {
        self::$errors = [];

        // Validate file upload
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            self::$errors[] = ['code' => 'UPLOAD_ERROR', 'message' => 'No file uploaded or upload error', 'field' => 'avatar'];
            return null;
        }

        // Validate file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            self::$errors[] = ['code' => 'INVALID_TYPE', 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions), 'field' => 'avatar'];
            return null;
        }

        // Validate MIME type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedMimes)) {
            self::$errors[] = ['code' => 'INVALID_TYPE', 'message' => 'Invalid file type', 'field' => 'avatar'];
            return null;
        }

        // Validate it's actually an image
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            self::$errors[] = ['code' => 'INVALID_IMAGE', 'message' => 'File is not a valid image', 'field' => 'avatar'];
            return null;
        }

        // Create upload directory
        $tenantId = TenantContext::getId();
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/{$tenantId}/avatars/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate secure filename
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            self::$errors[] = ['code' => 'UPLOAD_FAILED', 'message' => 'Failed to save file', 'field' => 'avatar'];
            return null;
        }

        // Update user record
        $avatarUrl = "/uploads/{$tenantId}/avatars/{$filename}";
        User::updateAvatar($userId, $avatarUrl);

        return $avatarUrl;
    }

    /**
     * Check if two users are connected
     */
    private static function areConnected(int $userId1, int $userId2): bool
    {
        $connection = Database::query(
            "SELECT id FROM connections
             WHERE ((requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?))
             AND status = 'accepted'",
            [$userId1, $userId2, $userId2, $userId1]
        )->fetch();

        return $connection !== false;
    }

    /**
     * Get connection status between two users
     */
    private static function getConnectionStatus(int $targetUserId, int $viewerId): string
    {
        $connection = Database::query(
            "SELECT status, requester_id FROM connections
             WHERE ((requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?))",
            [$viewerId, $targetUserId, $targetUserId, $viewerId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$connection) {
            return 'none';
        }

        if ($connection['status'] === 'accepted') {
            return 'connected';
        }

        if ($connection['status'] === 'pending') {
            if ((int)$connection['requester_id'] === $viewerId) {
                return 'pending_sent';
            } else {
                return 'pending_received';
            }
        }

        return 'none';
    }

    /**
     * Verify user's current password
     */
    public static function verifyPassword(int $userId, string $password): bool
    {
        return User::verifyPassword($userId, $password);
    }

    /**
     * Update user's password
     *
     * @param int $userId
     * @param string $currentPassword
     * @param string $newPassword
     * @return bool
     */
    public static function updatePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        self::$errors = [];

        // Verify current password
        if (!User::verifyPassword($userId, $currentPassword)) {
            self::$errors[] = ['code' => 'INVALID_PASSWORD', 'message' => 'Current password is incorrect', 'field' => 'current_password'];
            return false;
        }

        // Validate new password
        if (strlen($newPassword) < 8) {
            self::$errors[] = ['code' => 'WEAK_PASSWORD', 'message' => 'Password must be at least 8 characters', 'field' => 'new_password'];
            return false;
        }

        User::updatePassword($userId, $newPassword);

        return true;
    }

    /**
     * Delete user account permanently
     *
     * @param int $userId
     * @return bool
     */
    public static function deleteAccount(int $userId): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        // Verify user exists
        $user = User::findById($userId);
        if (!$user) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
            return false;
        }

        try {
            Database::beginTransaction();

            // Soft-delete approach: anonymize rather than hard delete
            // This preserves transaction history integrity

            // Anonymize user data
            $anonymizedEmail = 'deleted_' . $userId . '_' . time() . '@deleted.local';
            $anonymizedName = 'Deleted User';

            Database::query(
                "UPDATE users SET
                    email = ?,
                    first_name = ?,
                    last_name = '',
                    password = '',
                    bio = NULL,
                    location = NULL,
                    phone = NULL,
                    avatar = NULL,
                    status = 'deleted',
                    deleted_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?",
                [$anonymizedEmail, $anonymizedName, $userId, $tenantId]
            );

            // Delete sensitive data
            Database::query("DELETE FROM user_sessions WHERE user_id = ?", [$userId]);
            Database::query("DELETE FROM user_tokens WHERE user_id = ?", [$userId]);
            Database::query("DELETE FROM password_resets WHERE user_id = ?", [$userId]);

            // Anonymize messages (keep for other party's history)
            Database::query(
                "UPDATE messages SET sender_id = NULL WHERE sender_id = ?",
                [$userId]
            );

            // Remove from groups and connections
            Database::query("DELETE FROM group_members WHERE user_id = ?", [$userId]);
            Database::query("DELETE FROM connections WHERE user_id = ? OR connected_user_id = ?", [$userId, $userId]);

            Database::commit();

            return true;
        } catch (\Exception $e) {
            Database::rollback();
            error_log("Failed to delete account for user $userId: " . $e->getMessage());
            self::$errors[] = ['code' => 'DELETE_FAILED', 'message' => 'Failed to delete account'];
            return false;
        }
    }
}
