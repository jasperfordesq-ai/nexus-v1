<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Models\User;
use Nexus\Models\ActivityLog;

/**
 * AdminUsersApiController - V2 API for React admin user management
 *
 * Provides full user CRUD, status management, and moderation actions.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/users                     - List users (paginated, filterable)
 * - GET    /api/v2/admin/users/{id}                - Get single user detail
 * - POST   /api/v2/admin/users                     - Create user
 * - PUT    /api/v2/admin/users/{id}                - Update user
 * - DELETE /api/v2/admin/users/{id}                - Delete user
 * - POST   /api/v2/admin/users/{id}/approve        - Approve pending user
 * - POST   /api/v2/admin/users/{id}/suspend        - Suspend user
 * - POST   /api/v2/admin/users/{id}/ban            - Ban user
 * - POST   /api/v2/admin/users/{id}/reactivate     - Reactivate suspended/banned user
 * - POST   /api/v2/admin/users/{id}/reset-2fa      - Reset user's 2FA
 */
class AdminUsersApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/users
     *
     * Query params: page, limit, status (all|pending|active|suspended|banned), search, role, sort, order
     */
    public function index(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        $role = $_GET['role'] ?? null;
        $sort = $_GET['sort'] ?? 'created_at';
        $order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        // Whitelist sort columns
        $allowedSorts = ['name', 'email', 'role', 'created_at', 'balance', 'status'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'created_at';
        }

        $conditions = ['u.tenant_id = ?'];
        $params = [$tenantId];

        // Status filter
        if ($status && $status !== 'all') {
            switch ($status) {
                case 'pending':
                    $conditions[] = 'u.is_approved = 0';
                    break;
                case 'active':
                    $conditions[] = "u.is_approved = 1 AND (u.status IS NULL OR u.status = 'active')";
                    break;
                case 'suspended':
                    $conditions[] = "u.status = 'suspended'";
                    break;
                case 'banned':
                    $conditions[] = "u.status = 'banned'";
                    break;
            }
        }

        // Role filter
        if ($role) {
            $conditions[] = 'u.role = ?';
            $params[] = $role;
        }

        // Search filter
        if ($search) {
            $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $where = implode(' AND ', $conditions);

        // Map 'name' sort to computed column
        $sortColumn = $sort === 'name' ? 'name' : "u.{$sort}";
        // Map 'status' sort to computed expression
        if ($sort === 'status') {
            $sortColumn = "CASE WHEN u.is_approved = 0 THEN 'pending' WHEN u.status = 'suspended' THEN 'suspended' WHEN u.status = 'banned' THEN 'banned' ELSE 'active' END";
        }

        // Get total count
        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users u WHERE {$where}",
            $params
        )->fetch()['cnt'];

        // Get paginated users
        $users = Database::query(
            "SELECT u.id, u.first_name, u.last_name,
                CASE
                    WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                        THEN u.organization_name
                    ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
                END as name,
                u.email, u.avatar_url, u.location, u.role, u.is_approved, u.is_super_admin,
                u.status, u.created_at, u.last_active_at, u.profile_type, u.organization_name,
                COALESCE(u.balance, 0) as balance,
                (SELECT COUNT(*) FROM listings l WHERE l.user_id = u.id AND l.status = 'active') as listing_count
             FROM users u
             WHERE {$where}
             ORDER BY {$sortColumn} {$order}
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        )->fetchAll();

        // Check if 2FA columns exist
        $has2fa = false;
        try {
            $check = Database::query("SHOW COLUMNS FROM users LIKE 'totp_secret'");
            $has2fa = $check->rowCount() > 0;
        } catch (\Throwable $e) {
            // ignore
        }

        // Format for frontend
        $formatted = array_map(function ($row) use ($has2fa) {
            $status = 'active';
            if (!$row['is_approved']) {
                $status = 'pending';
            } elseif ($row['status'] === 'suspended') {
                $status = 'suspended';
            } elseif ($row['status'] === 'banned') {
                $status = 'banned';
            }

            return [
                'id' => (int) $row['id'],
                'name' => trim($row['name']),
                'first_name' => $row['first_name'] ?? '',
                'last_name' => $row['last_name'] ?? '',
                'email' => $row['email'],
                'avatar_url' => $row['avatar_url'] ?? null,
                'location' => $row['location'] ?? null,
                'role' => $row['role'] ?? 'member',
                'status' => $status,
                'is_super_admin' => (bool) ($row['is_super_admin'] ?? false),
                'balance' => (float) ($row['balance'] ?? 0),
                'listing_count' => (int) ($row['listing_count'] ?? 0),
                'profile_type' => $row['profile_type'] ?? 'individual',
                'has_2fa_enabled' => $has2fa ? !empty($row['totp_secret'] ?? null) : false,
                'created_at' => $row['created_at'],
                'last_active_at' => $row['last_active_at'] ?? null,
            ];
        }, $users);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/users/{id}
     */
    public function show(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $user = Database::query(
            "SELECT * FROM users WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        $status = 'active';
        if (!$user['is_approved']) {
            $status = 'pending';
        } elseif ($user['status'] === 'suspended') {
            $status = 'suspended';
        } elseif ($user['status'] === 'banned') {
            $status = 'banned';
        }

        // Fetch user badges
        $badges = [];
        try {
            $badgeRows = Database::query(
                "SELECT ub.id, ub.badge_key, ub.awarded_at, ub.badge_name, ub.badge_description, ub.badge_icon
                 FROM user_badges ub
                 WHERE ub.user_id = ?
                 ORDER BY ub.awarded_at DESC",
                [$id]
            )->fetchAll();

            $badges = array_map(function ($b) {
                return [
                    'id' => (int) $b['id'],
                    'name' => $b['badge_name'] ?? $b['badge_key'] ?? '',
                    'slug' => $b['badge_key'] ?? '',
                    'description' => $b['badge_description'] ?? '',
                    'icon' => $b['badge_icon'] ?? null,
                    'awarded_at' => $b['awarded_at'] ?? '',
                ];
            }, $badgeRows);
        } catch (\Throwable $e) {
            // user_badges table may not exist or have different schema
        }

        $this->respondWithData([
            'id' => (int) $user['id'],
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'email' => $user['email'],
            'avatar_url' => $user['avatar_url'] ?? null,
            'location' => $user['location'] ?? null,
            'bio' => $user['bio'] ?? null,
            'tagline' => $user['tagline'] ?? null,
            'phone' => $user['phone'] ?? null,
            'role' => $user['role'] ?? 'member',
            'status' => $status,
            'is_super_admin' => (bool) ($user['is_super_admin'] ?? false),
            'is_admin' => in_array($user['role'] ?? '', ['admin', 'tenant_admin']),
            'balance' => (float) ($user['balance'] ?? 0),
            'profile_type' => $user['profile_type'] ?? 'individual',
            'organization_name' => $user['organization_name'] ?? null,
            'badges' => $badges,
            'created_at' => $user['created_at'],
            'last_active_at' => $user['last_active_at'] ?? null,
        ]);
    }

    /**
     * POST /api/v2/admin/users
     *
     * Body: { first_name, last_name, email, password, role?, status? }
     */
    public function store(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();

        $firstName = trim($input['first_name'] ?? '');
        $lastName = trim($input['last_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'member';
        $location = trim($input['location'] ?? '');

        // Auto-generate password if not provided
        if (empty($password)) {
            $password = bin2hex(random_bytes(12));
        }

        // Validation
        $errors = [];
        if (empty($firstName)) {
            $errors[] = ['code' => ApiErrorCodes::VALIDATION_ERROR, 'message' => 'First name is required', 'field' => 'first_name'];
        }
        if (empty($lastName)) {
            $errors[] = ['code' => ApiErrorCodes::VALIDATION_ERROR, 'message' => 'Last name is required', 'field' => 'last_name'];
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['code' => ApiErrorCodes::VALIDATION_ERROR, 'message' => 'Valid email is required', 'field' => 'email'];
        }
        if (strlen($password) < 8) {
            $errors[] = ['code' => ApiErrorCodes::VALIDATION_ERROR, 'message' => 'Password must be at least 8 characters', 'field' => 'password'];
        }

        if (!empty($errors)) {
            $this->respondWithErrors($errors, 422);
            return;
        }

        // Check duplicate email
        $existing = User::findByEmail($email);
        if ($existing) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'A user with this email already exists', 'email', 422);
            return;
        }

        // Create user
        User::create($firstName, $lastName, $email, $password, $location ?: null);
        $newUser = User::findByEmail($email);

        if ($newUser) {
            User::updateAdminFields($newUser['id'], $role, 1);

            ActivityLog::log($adminId, 'admin_create_user', "Created user: {$email}");

            $this->respondWithData([
                'id' => (int) $newUser['id'],
                'name' => trim($firstName . ' ' . $lastName),
                'email' => $email,
                'role' => $role,
                'status' => 'active',
            ], null, 201);
        } else {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create user', null, 500);
        }
    }

    /**
     * PUT /api/v2/admin/users/{id}
     *
     * Body: { first_name?, last_name?, email?, role?, location?, phone?, bio? }
     */
    public function update(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id);
        if (!$user || $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        $input = $this->getAllInput();

        $updates = [];
        $params = [];

        if (isset($input['first_name'])) {
            $updates[] = 'first_name = ?';
            $params[] = trim($input['first_name']);
        }
        if (isset($input['last_name'])) {
            $updates[] = 'last_name = ?';
            $params[] = trim($input['last_name']);
        }
        if (isset($input['email'])) {
            $email = trim($input['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid email', 'email', 422);
                return;
            }
            $updates[] = 'email = ?';
            $params[] = $email;
        }
        if (isset($input['role'])) {
            $updates[] = 'role = ?';
            $params[] = $input['role'];
        }
        if (isset($input['location'])) {
            $updates[] = 'location = ?';
            $params[] = trim($input['location']);
        }
        if (isset($input['phone'])) {
            $updates[] = 'phone = ?';
            $params[] = trim($input['phone']);
        }
        if (isset($input['bio'])) {
            $updates[] = 'bio = ?';
            $params[] = trim($input['bio']);
        }
        if (isset($input['tagline'])) {
            $updates[] = 'tagline = ?';
            $params[] = trim($input['tagline']);
        }
        if (isset($input['status'])) {
            $allowedStatuses = ['active', 'suspended', 'banned'];
            if (in_array($input['status'], $allowedStatuses)) {
                $updates[] = 'status = ?';
                $params[] = $input['status'];
                // Also update is_approved for active status
                if ($input['status'] === 'active') {
                    $updates[] = 'is_approved = 1';
                }
            }
        }

        if (empty($updates)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No fields to update', null, 422);
            return;
        }

        $params[] = $id;
        $params[] = $tenantId;

        Database::query(
            "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        ActivityLog::log($adminId, 'admin_update_user', "Updated user #{$id}");

        // Return updated user
        $this->show($id);
    }

    /**
     * DELETE /api/v2/admin/users/{id}
     */
    public function destroy(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Prevent self-deletion
        if ($id === $adminId) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot delete your own account', null, 403);
            return;
        }

        $user = User::findById($id);
        if (!$user || $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Prevent deleting super admins unless caller is super admin
        if (!empty($user['is_super_admin'])) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot delete a super admin', null, 403);
            return;
        }

        Database::query("DELETE FROM users WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        ActivityLog::log($adminId, 'admin_delete_user', "Deleted user #{$id} ({$user['email']})");

        $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/users/{id}/approve
     */
    public function approve(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id);
        if (!$user || $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        User::updateAdminFields($id, $user['role'] ?? 'member', 1);

        ActivityLog::log($adminId, 'admin_approve_user', "Approved user #{$id} ({$user['email']})");

        $this->respondWithData(['approved' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/users/{id}/suspend
     *
     * Body: { reason? }
     */
    public function suspend(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if ($id === $adminId) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot suspend your own account', null, 403);
            return;
        }

        $user = User::findById($id);
        if (!$user || $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        if (!empty($user['is_super_admin'])) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot suspend a super admin', null, 403);
            return;
        }

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Suspended by admin';

        Database::query(
            "UPDATE users SET status = 'suspended' WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        ActivityLog::log($adminId, 'admin_suspend_user', "Suspended user #{$id}: {$reason}");

        $this->respondWithData(['suspended' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/users/{id}/ban
     *
     * Body: { reason? }
     */
    public function ban(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if ($id === $adminId) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot ban your own account', null, 403);
            return;
        }

        $user = User::findById($id);
        if (!$user || $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        if (!empty($user['is_super_admin'])) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot ban a super admin', null, 403);
            return;
        }

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Banned by admin';

        Database::query(
            "UPDATE users SET status = 'banned' WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        ActivityLog::log($adminId, 'admin_ban_user', "Banned user #{$id}: {$reason}");

        $this->respondWithData(['banned' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/users/{id}/reactivate
     */
    public function reactivate(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id);
        if (!$user || $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        Database::query(
            "UPDATE users SET status = 'active', is_approved = 1 WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        ActivityLog::log($adminId, 'admin_reactivate_user', "Reactivated user #{$id} ({$user['email']})");

        $this->respondWithData(['reactivated' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/users/{id}/reset-2fa
     *
     * Body: { reason }
     */
    public function reset2fa(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id);
        if (!$user || $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Reset by admin';

        // Clear TOTP secret and backup codes
        try {
            Database::query(
                "UPDATE users SET totp_secret = NULL, totp_backup_codes = NULL WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
        } catch (\Throwable $e) {
            // totp columns may not exist
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, '2FA columns not available', null, 500);
            return;
        }

        ActivityLog::log($adminId, 'admin_reset_2fa', "Reset 2FA for user #{$id}: {$reason}");

        $this->respondWithData(['reset' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/users/{id}/badges
     *
     * Award a badge to a user.
     * Body: { badge_slug }
     */
    public function addBadge(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id);
        if (!$user || $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        $input = $this->getAllInput();
        $badgeSlug = trim($input['badge_slug'] ?? '');

        if (empty($badgeSlug)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Badge slug is required', 'badge_slug', 422);
            return;
        }

        try {
            \Nexus\Services\GamificationService::awardBadgeByKey($id, $badgeSlug);

            ActivityLog::log($adminId, 'admin_award_badge', "Awarded badge '{$badgeSlug}' to user #{$id}");

            $this->respondWithData([
                'awarded' => true,
                'user_id' => $id,
                'badge_slug' => $badgeSlug,
            ], null, 201);
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to award badge: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * DELETE /api/v2/admin/users/{id}/badges/{badgeId}
     *
     * Remove a badge from a user.
     */
    public function removeBadge(int $id, int $badgeId): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id);
        if (!$user || $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        try {
            $badge = Database::query(
                "SELECT * FROM user_badges WHERE id = ? AND user_id = ?",
                [$badgeId, $id]
            )->fetch();

            if (!$badge) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Badge not found for this user', null, 404);
                return;
            }

            Database::query("DELETE FROM user_badges WHERE id = ? AND user_id = ?", [$badgeId, $id]);

            ActivityLog::log($adminId, 'admin_remove_badge', "Removed badge #{$badgeId} from user #{$id}");

            $this->respondWithData(['removed' => true, 'user_id' => $id, 'badge_id' => $badgeId]);
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to remove badge: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/v2/admin/users/{id}/impersonate
     *
     * Generate an impersonation token to view the platform as a specific user.
     * Returns a short-lived JWT token for the target user.
     */
    public function impersonate(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id);
        if (!$user || $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Prevent impersonating super admins (security measure)
        if (!empty($user['is_super_admin'])) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot impersonate a super admin', null, 403);
            return;
        }

        // Prevent self-impersonation
        if ($id === $adminId) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Cannot impersonate yourself', null, 422);
            return;
        }

        try {
            // Generate an access token for the target user with impersonation claim
            $token = \Nexus\Services\TokenService::generateToken($id, $tenantId, [
                'impersonated_by' => $adminId,
            ]);

            ActivityLog::log($adminId, 'admin_impersonate', "Impersonated user #{$id} ({$user['email']})");

            $this->respondWithData([
                'token' => $token,
                'user_id' => $id,
                'user_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            ]);
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to generate impersonation token', null, 500);
        }
    }
}
