<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class NewsletterSubscriber
{
    /**
     * Generate a secure random token
     */
    private static function generateToken()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Create a new subscriber (pending confirmation)
     */
    public static function create($email, $firstName = null, $lastName = null, $source = 'signup', $userId = null)
    {
        $tenantId = TenantContext::getId();

        // Check if already exists
        $existing = self::findByEmail($email);
        if ($existing) {
            // If unsubscribed, allow re-subscription
            if ($existing['status'] === 'unsubscribed') {
                self::resubscribe($existing['id']);
                return $existing['id'];
            }
            return $existing['id'];
        }

        $confirmationToken = self::generateToken();
        $unsubscribeToken = self::generateToken();

        $sql = "INSERT INTO newsletter_subscribers
                (tenant_id, email, first_name, last_name, user_id, source, confirmation_token, unsubscribe_token, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

        Database::query($sql, [
            $tenantId,
            strtolower(trim($email)),
            $firstName,
            $lastName,
            $userId,
            $source,
            $confirmationToken,
            $unsubscribeToken
        ]);

        return Database::getConnection()->lastInsertId();
    }

    /**
     * Create subscriber and immediately confirm (for imports/manual adds)
     */
    public static function createConfirmed($email, $firstName = null, $lastName = null, $source = 'manual', $userId = null)
    {
        $tenantId = TenantContext::getId();

        // Check if already exists
        $existing = self::findByEmail($email);
        if ($existing) {
            if ($existing['status'] === 'unsubscribed') {
                self::resubscribe($existing['id']);
            }
            return $existing['id'];
        }

        $unsubscribeToken = self::generateToken();

        $sql = "INSERT INTO newsletter_subscribers
                (tenant_id, email, first_name, last_name, user_id, source, unsubscribe_token, status, confirmed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";

        Database::query($sql, [
            $tenantId,
            strtolower(trim($email)),
            $firstName,
            $lastName,
            $userId,
            $source,
            $unsubscribeToken
        ]);

        return Database::getConnection()->lastInsertId();
    }

    /**
     * Find subscriber by email (tenant-scoped)
     */
    public static function findByEmail($email)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT * FROM newsletter_subscribers WHERE tenant_id = ? AND email = ?";
        return Database::query($sql, [$tenantId, strtolower(trim($email))])->fetch();
    }

    /**
     * Find subscriber by ID
     */
    public static function findById($id)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT * FROM newsletter_subscribers WHERE id = ? AND tenant_id = ?";
        return Database::query($sql, [$id, $tenantId])->fetch();
    }

    /**
     * Find by confirmation token (no tenant scope - token is unique)
     */
    public static function findByConfirmationToken($token)
    {
        $sql = "SELECT * FROM newsletter_subscribers WHERE confirmation_token = ?";
        return Database::query($sql, [$token])->fetch();
    }

    /**
     * Find by unsubscribe token (no tenant scope - token is unique)
     */
    public static function findByUnsubscribeToken($token)
    {
        $sql = "SELECT * FROM newsletter_subscribers WHERE unsubscribe_token = ?";
        return Database::query($sql, [$token])->fetch();
    }

    /**
     * Confirm subscription (double opt-in complete)
     */
    public static function confirm($token)
    {
        $subscriber = self::findByConfirmationToken($token);
        if (!$subscriber) {
            return false;
        }

        $sql = "UPDATE newsletter_subscribers
                SET status = 'active', confirmed_at = NOW(), confirmation_token = NULL
                WHERE id = ?";
        Database::query($sql, [$subscriber['id']]);

        return $subscriber;
    }

    /**
     * Unsubscribe via token
     */
    public static function unsubscribe($token, $reason = null)
    {
        $subscriber = self::findByUnsubscribeToken($token);
        if (!$subscriber) {
            return false;
        }

        $sql = "UPDATE newsletter_subscribers
                SET status = 'unsubscribed', unsubscribed_at = NOW(), unsubscribe_reason = ?
                WHERE id = ?";
        Database::query($sql, [$reason, $subscriber['id']]);

        return $subscriber;
    }

    /**
     * Resubscribe a previously unsubscribed email
     */
    public static function resubscribe($id)
    {
        $sql = "UPDATE newsletter_subscribers
                SET status = 'pending',
                    unsubscribed_at = NULL,
                    unsubscribe_reason = NULL,
                    confirmation_token = ?
                WHERE id = ?";
        Database::query($sql, [self::generateToken(), $id]);
    }

    /**
     * Get all active subscribers for current tenant
     */
    public static function getActive($limit = 1000, $offset = 0)
    {
        $tenantId = TenantContext::getId();
        $limit = (int)$limit;
        $offset = (int)$offset;

        $sql = "SELECT * FROM newsletter_subscribers
                WHERE tenant_id = ? AND status = 'active'
                ORDER BY created_at DESC
                LIMIT $limit OFFSET $offset";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Get all subscribers (any status) for admin view
     */
    public static function getAll($limit = 100, $offset = 0, $status = null)
    {
        $tenantId = TenantContext::getId();
        $limit = (int)$limit;
        $offset = (int)$offset;

        if ($status) {
            $sql = "SELECT ns.*, u.first_name as member_first_name, u.last_name as member_last_name
                    FROM newsletter_subscribers ns
                    LEFT JOIN users u ON ns.user_id = u.id
                    WHERE ns.tenant_id = ? AND ns.status = ?
                    ORDER BY ns.created_at DESC
                    LIMIT $limit OFFSET $offset";
            return Database::query($sql, [$tenantId, $status])->fetchAll();
        }

        $sql = "SELECT ns.*, u.first_name as member_first_name, u.last_name as member_last_name
                FROM newsletter_subscribers ns
                LEFT JOIN users u ON ns.user_id = u.id
                WHERE ns.tenant_id = ?
                ORDER BY ns.created_at DESC
                LIMIT $limit OFFSET $offset";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Count subscribers by status
     */
    public static function count($status = null)
    {
        $tenantId = TenantContext::getId();

        if ($status) {
            $sql = "SELECT COUNT(*) as total FROM newsletter_subscribers WHERE tenant_id = ? AND status = ?";
            return Database::query($sql, [$tenantId, $status])->fetch()['total'];
        }

        $sql = "SELECT COUNT(*) as total FROM newsletter_subscribers WHERE tenant_id = ?";
        return Database::query($sql, [$tenantId])->fetch()['total'];
    }

    /**
     * Get subscriber statistics
     */
    public static function getStats()
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed,
                    SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) as members,
                    SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as external
                FROM newsletter_subscribers
                WHERE tenant_id = ?";

        return Database::query($sql, [$tenantId])->fetch();
    }

    /**
     * Delete subscriber
     */
    public static function delete($id)
    {
        $tenantId = TenantContext::getId();
        $sql = "DELETE FROM newsletter_subscribers WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$id, $tenantId]);
    }

    /**
     * Sync existing members to subscriber list
     */
    public static function syncMembers()
    {
        $tenantId = TenantContext::getId();

        // DETAILED DEBUG LOGGING
        error_log("=== NEWSLETTER SYNC START ===");
        error_log("Tenant ID: $tenantId");

        // Step 1: Total users in tenant
        $totalUsers = Database::query("SELECT COUNT(*) as c FROM users WHERE tenant_id = ?", [$tenantId])->fetch()['c'];
        error_log("Step 1 - Total users in tenant $tenantId: $totalUsers");

        // Step 2: Users with is_approved = 1
        $approvedUsers = Database::query("SELECT COUNT(*) as c FROM users WHERE tenant_id = ? AND is_approved = 1", [$tenantId])->fetch()['c'];
        error_log("Step 2 - Users with is_approved=1: $approvedUsers");

        // Step 3: Users with valid email
        $usersWithEmail = Database::query("SELECT COUNT(*) as c FROM users WHERE tenant_id = ? AND email IS NOT NULL AND TRIM(email) != ''", [$tenantId])->fetch()['c'];
        error_log("Step 3 - Users with valid email: $usersWithEmail");

        // Step 4: Existing subscribers
        $existingSubscribers = Database::query("SELECT COUNT(*) as c FROM newsletter_subscribers WHERE tenant_id = ?", [$tenantId])->fetch()['c'];
        error_log("Step 4 - Existing subscribers: $existingSubscribers");

        // Step 5: Sample of user emails vs subscriber emails
        $sampleUsers = Database::query("SELECT id, email, is_approved FROM users WHERE tenant_id = ? LIMIT 5", [$tenantId])->fetchAll();
        error_log("Step 5 - Sample users: " . json_encode($sampleUsers));

        $sampleSubs = Database::query("SELECT id, email, user_id FROM newsletter_subscribers WHERE tenant_id = ? LIMIT 5", [$tenantId])->fetchAll();
        error_log("Step 5 - Sample subscribers: " . json_encode($sampleSubs));

        // Debug: Check how many eligible users exist
        // Sync ALL platform members regardless of approval status - they are registered users
        $debugSql = "SELECT COUNT(*) as total FROM users u
                     WHERE u.tenant_id = ?
                     AND u.email IS NOT NULL
                     AND TRIM(u.email) != ''
                     AND NOT EXISTS (
                         SELECT 1 FROM newsletter_subscribers ns
                         WHERE ns.tenant_id = u.tenant_id AND LOWER(TRIM(ns.email)) = LOWER(TRIM(u.email))
                     )";
        $debugResult = Database::query($debugSql, [$tenantId])->fetch();
        error_log("Step 6 - Eligible users (not yet subscribers): " . ($debugResult['total'] ?? 0));

        // Get all users with emails who aren't already subscribers
        // All registered platform members should be synced regardless of approval status
        $selectSql = "SELECT u.tenant_id, LOWER(TRIM(u.email)) as email, u.first_name, u.last_name, u.id as user_id
                FROM users u
                WHERE u.tenant_id = ?
                AND u.email IS NOT NULL
                AND TRIM(u.email) != ''
                AND NOT EXISTS (
                    SELECT 1 FROM newsletter_subscribers ns
                    WHERE ns.tenant_id = u.tenant_id AND LOWER(TRIM(ns.email)) = LOWER(TRIM(u.email))
                )";

        try {
            $usersToSync = Database::query($selectSql, [$tenantId])->fetchAll();
            $count = 0;

            $insertSql = "INSERT INTO newsletter_subscribers (tenant_id, email, first_name, last_name, user_id, source, unsubscribe_token, status, confirmed_at)
                          VALUES (?, ?, ?, ?, ?, 'member_sync', ?, 'active', NOW())";

            foreach ($usersToSync as $user) {
                // Generate token with PHP instead of RANDOM_BYTES()
                $token = bin2hex(random_bytes(32));
                Database::query($insertSql, [
                    $user['tenant_id'],
                    $user['email'],
                    $user['first_name'],
                    $user['last_name'],
                    $user['user_id'],
                    $token
                ]);
                $count++;
            }

            error_log("Newsletter Sync - Inserted $count new subscribers for tenant $tenantId");

            // Also link existing subscribers to their user accounts if not already linked
            $linkSql = "UPDATE newsletter_subscribers ns
                        JOIN users u ON ns.tenant_id = u.tenant_id
                            AND LOWER(TRIM(ns.email)) = LOWER(TRIM(u.email))
                        SET ns.user_id = u.id
                        WHERE ns.tenant_id = ?
                        AND ns.user_id IS NULL";
            $linkStmt = Database::query($linkSql, [$tenantId]);
            $linked = $linkStmt->rowCount();
            if ($linked > 0) {
                error_log("Newsletter Sync - Linked $linked existing subscribers to user accounts for tenant $tenantId");
            }

            return $count;
        } catch (\Exception $e) {
            error_log("Member sync error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Sync members with detailed stats for better user feedback
     */
    public static function syncMembersWithStats()
    {
        $tenantId = TenantContext::getId();

        // Debug logging
        error_log("=== syncMembersWithStats DEBUG ===");
        error_log("TenantContext::getId() = " . var_export($tenantId, true));
        error_log("SESSION tenant_id = " . var_export($_SESSION['tenant_id'] ?? null, true));

        // Count APPROVED users in tenant with valid emails (eligible for sync)
        $totalUsers = Database::query(
            "SELECT COUNT(*) as c FROM users WHERE tenant_id = ? AND is_approved = 1 AND email IS NOT NULL AND TRIM(email) != ''",
            [$tenantId]
        )->fetch()['c'];

        error_log("Total approved users with valid emails: $totalUsers");

        // Count pending approval users
        $pendingApproval = Database::query(
            "SELECT COUNT(*) as c FROM users WHERE tenant_id = ? AND is_approved = 0 AND email IS NOT NULL AND TRIM(email) != ''",
            [$tenantId]
        )->fetch()['c'];

        // Count existing subscribers
        $alreadySubscribed = Database::query(
            "SELECT COUNT(*) as c FROM newsletter_subscribers WHERE tenant_id = ?",
            [$tenantId]
        )->fetch()['c'];

        // Count eligible (approved and not yet subscribed)
        $eligible = Database::query(
            "SELECT COUNT(*) as c FROM users u
             WHERE u.tenant_id = ?
             AND u.is_approved = 1
             AND u.email IS NOT NULL
             AND TRIM(u.email) != ''
             AND NOT EXISTS (
                 SELECT 1 FROM newsletter_subscribers ns
                 WHERE ns.tenant_id = u.tenant_id AND LOWER(TRIM(ns.email)) = LOWER(TRIM(u.email))
             )",
            [$tenantId]
        )->fetch()['c'];

        // Perform the sync
        $synced = self::syncMembers();

        return [
            'synced' => $synced,
            'total_users' => $totalUsers,
            'already_subscribed' => $alreadySubscribed,
            'eligible' => $eligible,
            'pending_approval' => $pendingApproval
        ];
    }

    /**
     * Import subscribers from array
     */
    public static function import($subscribers)
    {
        $imported = 0;
        $skipped = 0;

        foreach ($subscribers as $sub) {
            $email = $sub['email'] ?? null;
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $existing = self::findByEmail($email);
            if ($existing) {
                $skipped++;
                continue;
            }

            self::createConfirmed(
                $email,
                $sub['first_name'] ?? null,
                $sub['last_name'] ?? null,
                'import'
            );
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Export active subscribers as array
     */
    public static function export()
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT email, first_name, last_name, status, source, created_at, confirmed_at
                FROM newsletter_subscribers
                WHERE tenant_id = ? AND status = 'active'
                ORDER BY email ASC";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Update subscriber details
     */
    public static function update($id, $data)
    {
        $tenantId = TenantContext::getId();
        $fields = [];
        $params = [];

        $allowedFields = ['first_name', 'last_name', 'status'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($fields)) return false;

        $sql = "UPDATE newsletter_subscribers SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?";
        $params[] = $id;
        $params[] = $tenantId;

        Database::query($sql, $params);
        return true;
    }
}
