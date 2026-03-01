<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Models\User;
use Nexus\Models\Notification;
use Nexus\Models\ActivityLog;
use Nexus\Services\AuditLogService;
use Nexus\Services\TenantSettingsService;

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
 * - POST   /api/v2/admin/users/import              - Bulk import users from CSV
 * - GET    /api/v2/admin/users/import/template      - Download CSV import template
 * - POST   /api/v2/admin/users/{id}/badges/recheck  - Recheck badges for single user
 * - GET    /api/v2/admin/users/{id}/consents         - Get user GDPR consents
 * - POST   /api/v2/admin/users/{id}/password         - Admin set user password
 * - POST   /api/v2/admin/users/{id}/send-password-reset - Send password reset email
 * - POST   /api/v2/admin/users/{id}/send-welcome-email  - Resend welcome email
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
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

        $conditions = [];
        $params = [];

        // Tenant scoping: defaults to current tenant; super admins can pass ?tenant_id=all
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        if ($effectiveTenantId !== null) {
            $conditions[] = 'u.tenant_id = ?';
            $params[] = $effectiveTenantId;
        }

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

        $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

        // Map sort column via allowlist (prevents SQL injection even though $sort is already whitelisted)
        $sortColumnMap = [
            'name' => 'name',
            'email' => 'u.email',
            'role' => 'u.role',
            'created_at' => 'u.created_at',
            'balance' => 'COALESCE(u.balance, 0)',
            'status' => "CASE WHEN u.is_approved = 0 THEN 'pending' WHEN u.status = 'suspended' THEN 'suspended' WHEN u.status = 'banned' THEN 'banned' ELSE 'active' END",
        ];
        $sortColumn = $sortColumnMap[$sort] ?? 'u.created_at';

        // Get total count
        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users u WHERE {$where}",
            $params
        )->fetch()['cnt'];

        // Get paginated users — join tenants table for cross-tenant name display
        $users = Database::query(
            "SELECT u.id, u.first_name, u.last_name,
                CASE
                    WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                        THEN u.organization_name
                    ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
                END as name,
                u.email, u.avatar_url, u.location, u.role, u.is_approved, u.is_super_admin,
                u.status, u.created_at, u.last_active_at, u.profile_type, u.organization_name,
                u.tenant_id,
                t.name as tenant_name,
                COALESCE(u.balance, 0) as balance,
                (SELECT COUNT(*) FROM listings l WHERE l.user_id = u.id AND l.status = 'active') as listing_count
             FROM users u
             LEFT JOIN tenants t ON u.tenant_id = t.id
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
                'tenant_id' => (int) $row['tenant_id'],
                'tenant_name' => $row['tenant_name'] ?? 'Unknown',
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Super admins can view users from any tenant
        if ($isSuperAdmin) {
            $user = Database::query(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url, u.location, u.bio, u.tagline, u.phone,
                        u.role, u.status, u.is_approved, u.is_super_admin, u.balance, u.profile_type,
                        u.organization_name, u.vetting_status, u.insurance_status, u.created_at, u.last_active_at,
                        u.email_verified_at, u.is_verified,
                        u.tenant_id, t.name as tenant_name
                 FROM users u
                 LEFT JOIN tenants t ON u.tenant_id = t.id
                 WHERE u.id = ?",
                [$id]
            )->fetch();
        } else {
            $user = Database::query(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url, u.location, u.bio, u.tagline, u.phone,
                        u.role, u.status, u.is_approved, u.is_super_admin, u.balance, u.profile_type,
                        u.organization_name, u.vetting_status, u.insurance_status, u.created_at, u.last_active_at,
                        u.email_verified_at, u.is_verified,
                        u.tenant_id, t.name as tenant_name
                 FROM users u
                 LEFT JOIN tenants t ON u.tenant_id = t.id
                 WHERE u.id = ? AND u.tenant_id = ?",
                [$id, $tenantId]
            )->fetch();
        }

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
            'tenant_id' => (int) $user['tenant_id'],
            'tenant_name' => $user['tenant_name'] ?? 'Unknown',
            'badges' => $badges,
            'is_approved' => (bool) ($user['is_approved'] ?? false),
            'email_verified_at' => $user['email_verified_at'] ?? null,
            'is_verified' => (bool) ($user['is_verified'] ?? false),
            'vetting_status' => $user['vetting_status'] ?? 'none',
            'insurance_status' => $user['insurance_status'] ?? 'none',
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
            AuditLogService::logUserCreated($adminId, (int) $newUser['id'], $email);

            // Record GDPR consents (admin-created accounts)
            try {
                $gdprService = new \Nexus\Services\Enterprise\GdprService($tenantId);
                $consentText = "Account created by administrator. User agrees to Terms of Service and Privacy Policy upon first login.";
                $consentVersion = '1.0';
                $gdprService->recordConsent((int) $newUser['id'], 'terms_of_service', true, $consentText, $consentVersion);
                $gdprService->recordConsent((int) $newUser['id'], 'privacy_policy', true, $consentText, $consentVersion);
            } catch (\Throwable $e) {
                error_log("GDPR Consent Recording Failed for admin-created user: " . $e->getMessage());
            }

            // Send welcome email if requested
            $sendWelcomeEmail = !empty($input['send_welcome_email']);
            if ($sendWelcomeEmail) {
                try {
                    $tenant = TenantContext::get();
                    $tenantName = $tenant['name'] ?? 'Project NEXUS';
                    $loginLink = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . "/login";

                    $html = \Nexus\Core\EmailTemplate::render(
                        "Your Account Has Been Created",
                        "Welcome to {$tenantName}!",
                        "<p>Hello <strong>" . htmlspecialchars($firstName) . "</strong>,</p>
                        <p>An administrator has created an account for you on {$tenantName}.</p>
                        <p>Your login credentials are:</p>
                        <ul>
                            <li><strong>Email:</strong> " . htmlspecialchars($email) . "</li>
                            <li><strong>Password:</strong> " . htmlspecialchars($password) . "</li>
                        </ul>
                        <p>We recommend changing your password after your first login.</p>",
                        "Login Now",
                        $loginLink,
                        "Project NEXUS"
                    );

                    $mailer = new \Nexus\Core\Mailer();
                    $mailer->send($email, "Your account on {$tenantName}", $html);
                } catch (\Throwable $e) {
                    error_log("Welcome email failed for admin-created user: " . $e->getMessage());
                }
            }

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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only update users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Use the user's own tenant_id for the UPDATE query (safety)
        $userTenantId = (int) $user['tenant_id'];

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
        if (isset($input['profile_type'])) {
            $allowedTypes = ['individual', 'organisation'];
            if (in_array($input['profile_type'], $allowedTypes)) {
                $updates[] = 'profile_type = ?';
                $params[] = $input['profile_type'];
            }
        }
        if (isset($input['organization_name'])) {
            $updates[] = 'organization_name = ?';
            $params[] = trim($input['organization_name']);
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
        $params[] = $userTenantId;

        Database::query(
            "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        ActivityLog::log($adminId, 'admin_update_user', "Updated user #{$id}" . ($isSuperAdmin ? " (tenant {$userTenantId})" : ''));

        // Audit log: track which fields changed
        $changedFields = array_keys($input);
        AuditLogService::logUserUpdated($adminId, $id, $changedFields);

        // Audit log: if role changed, log it specifically
        if (isset($input['role']) && ($user['role'] ?? 'member') !== $input['role']) {
            AuditLogService::logAdminRoleChanged($adminId, $id, $user['role'] ?? 'member', $input['role']);
        }

        // If user was just approved via status change (was unapproved, now active), trigger full welcome flow
        $wasUnapproved = empty($user['is_approved']);
        $nowApproved = isset($input['status']) && $input['status'] === 'active';
        if ($wasUnapproved && $nowApproved) {
            // Re-fetch to pick up any email/name changes applied in this same request
            $freshUser = User::findById($id, !$isSuperAdmin) ?? $user;
            $creditsAwarded = $this->grantWelcomeCredits($freshUser, $adminId);
            $this->sendApprovalWelcomeEmail($freshUser, $creditsAwarded);
            $this->sendApprovalInAppNotification($freshUser, $creditsAwarded);
            ActivityLog::log($adminId, 'admin_approve_user', "Approved user #{$id} ({$freshUser['email']}) via status update");
        }

        // Return updated user
        $this->show($id);
    }

    /**
     * DELETE /api/v2/admin/users/{id}
     */
    public function destroy(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Prevent self-deletion
        if ($id === $adminId) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot delete your own account', null, 403);
            return;
        }

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only delete users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Prevent deleting super admins unless caller is super admin
        if (!empty($user['is_super_admin'])) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot delete a super admin', null, 403);
            return;
        }

        // Use the user's own tenant_id for the DELETE query (safety)
        $userTenantId = (int) $user['tenant_id'];

        Database::query("DELETE FROM users WHERE id = ? AND tenant_id = ?", [$id, $userTenantId]);

        ActivityLog::log($adminId, 'admin_delete_user', "Deleted user #{$id} ({$user['email']})" . ($isSuperAdmin ? " (tenant {$userTenantId})" : ''));
        AuditLogService::logUserDeleted($adminId, $id, $user['email']);

        $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/users/{id}/approve
     */
    public function approve(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only approve users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Idempotency: prevent double-approval (and double welcome credits)
        if (!empty($user['is_approved'])) {
            $this->respondWithData(['approved' => true, 'id' => $id, 'already_approved' => true]);
            return;
        }

        User::updateAdminFields($id, $user['role'] ?? 'member', 1, null, (int) $user['tenant_id']);

        ActivityLog::log($adminId, 'admin_approve_user', "Approved user #{$id} ({$user['email']})" . ($isSuperAdmin ? " (tenant {$user['tenant_id']})" : ''));
        AuditLogService::logUserApproved($adminId, $id, $user['email']);

        // Grant welcome credits + send combined welcome email + in-app notification
        $creditsAwarded = $this->grantWelcomeCredits($user, $adminId);
        $emailSent = $this->sendApprovalWelcomeEmail($user, $creditsAwarded);
        $this->sendApprovalInAppNotification($user, $creditsAwarded);

        $this->respondWithData([
            'approved' => true,
            'id' => $id,
            'email_sent' => $emailSent,
            'welcome_credits' => $creditsAwarded,
        ]);
    }

    /**
     * Resolve the tenant name and slug for a user's tenant.
     * Uses the user's tenant_id to look up the correct values, which is critical
     * when a super admin approves a user from a different tenant.
     *
     * @param array $user User record from the database
     * @return array{tenant_id: int, name: string, slug_prefix: string}
     */
    private function resolveUserTenant(array $user): array
    {
        if (empty($user['tenant_id'])) {
            throw new \RuntimeException("User #{$user['id']} has no tenant_id — cannot resolve tenant for notifications");
        }
        $userTenantId = (int) $user['tenant_id'];
        $tenantName = 'Project NEXUS';
        $slugPrefix = '';

        $tenant = Database::query("SELECT name, slug FROM tenants WHERE id = ?", [$userTenantId])->fetch();
        if ($tenant) {
            $tenantName = $tenant['name'];
            $slug = $tenant['slug'] ?? '';
            $slugPrefix = $slug ? '/' . $slug : '';
        }

        return ['tenant_id' => $userTenantId, 'name' => $tenantName, 'slug_prefix' => $slugPrefix];
    }

    /**
     * Grant welcome time credits to a newly approved user.
     *
     * Amount is configurable per tenant via the 'welcome_credits' setting
     * (defaults to 5). Set to 0 to disable welcome credits for a tenant.
     *
     * Creates a transaction record for audit trail and updates the user's balance.
     * All queries are scoped by the USER's tenant_id (not the admin's).
     *
     * @param array $user User record from the database
     * @param int $adminId ID of the approving admin
     * @return int The number of credits awarded (0 if disabled or on error)
     */
    private function grantWelcomeCredits(array $user, int $adminId): int
    {
        try {
            if (empty($user['tenant_id'])) {
                throw new \RuntimeException("User #{$user['id']} has no tenant_id — cannot grant credits");
            }
            $userTenantId = (int) $user['tenant_id'];
            $userId = (int) $user['id'];

            // Read the welcome credits amount for the user's tenant (default: 5)
            $creditAmount = (int) TenantSettingsService::get($userTenantId, 'welcome_credits', 5);
            if ($creditAmount <= 0) {
                return 0;
            }

            $pdo = Database::getConnection();
            $pdo->beginTransaction();

            try {
                // Lock the user row to prevent concurrent double-credit (TOCTOU race).
                // The idempotency guard in approve() checks is_approved BEFORE we get here,
                // but two concurrent requests could both pass that check. This lock serializes them.
                Database::query(
                    "SELECT id FROM users WHERE id = ? AND tenant_id = ? FOR UPDATE",
                    [$userId, $userTenantId]
                );

                // Check if a welcome bonus was already granted (true idempotency key)
                $existing = Database::query(
                    "SELECT id FROM transactions WHERE tenant_id = ? AND receiver_id = ? AND description LIKE '[Welcome Bonus]%' LIMIT 1",
                    [$userTenantId, $userId]
                )->fetch();

                if ($existing) {
                    $pdo->rollBack();
                    error_log("[AdminUsers] Welcome credits already exist for user #{$userId} (tenant {$userTenantId}) — skipping");
                    return 0;
                }

                // Update user balance (scoped by user's tenant)
                Database::query(
                    "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                    [$creditAmount, $userId, $userTenantId]
                );

                // Create transaction record for audit trail.
                // Use userId as both sender and receiver (self-credit) so the record
                // stays within the user's tenant. Cross-tenant admin IDs would produce
                // orphaned sender references in wallet queries that join by tenant.
                Database::query(
                    "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, status, created_at)
                     VALUES (?, ?, ?, ?, ?, 'completed', NOW())",
                    [$userTenantId, $userId, $userId, $creditAmount, "[Welcome Bonus] New member welcome credits (approved by admin #{$adminId})"]
                );

                $pdo->commit();

                ActivityLog::log($adminId, 'welcome_credits_issued', "Awarded {$creditAmount} welcome credits to user #{$userId} ({$user['email']}) on approval");
                error_log("[AdminUsers] Granted {$creditAmount} welcome credits to user #{$userId} (tenant {$userTenantId})");

                return $creditAmount;
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to grant welcome credits to user #{$user['id']}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send the combined welcome email when a user is approved.
     * Includes the approval confirmation and welcome credits (if any were awarded).
     *
     * @param array $user User record from the database
     * @param int $creditsAwarded Number of welcome credits awarded (0 = none)
     * @return bool Whether the email was sent successfully
     */
    private function sendApprovalWelcomeEmail(array $user, int $creditsAwarded): bool
    {
        try {
            $tenant = $this->resolveUserTenant($user);
            $firstName = htmlspecialchars($user['first_name'] ?? 'there', ENT_QUOTES, 'UTF-8');
            $loginUrl = TenantContext::getFrontendUrl() . $tenant['slug_prefix'] . '/login';
            $tenantNameSafe = htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8');

            // Build the email body — include credit info if credits were awarded
            $body = "<p>Great news, {$firstName}! Your account has been approved and you're now a full member of the <strong>{$tenantNameSafe}</strong> community.</p>";

            if ($creditsAwarded > 0) {
                $body .= "<p>To help you get started, we've added <strong>{$creditsAwarded} time credit" . ($creditsAwarded !== 1 ? 's' : '') . "</strong> to your wallet. "
                       . "Use them to request services from other members, or earn more by offering your own skills and time.</p>";
            }

            $body .= '<p>Here are a few things you can do right away:</p>'
                    . '<ul style="padding-left: 20px; margin: 10px 0;">'
                    . '<li style="margin-bottom: 8px;">Browse <strong>listings</strong> to see what services are available</li>'
                    . '<li style="margin-bottom: 8px;">Create your own <strong>listing</strong> to offer your skills</li>'
                    . '<li style="margin-bottom: 8px;">Connect with other <strong>members</strong> in your community</li>'
                    . '<li style="margin-bottom: 8px;">Check out upcoming <strong>events</strong> and get involved</li>'
                    . '</ul>'
                    . "<p>We're glad to have you on board. Welcome to the community!</p>";

            $html = \Nexus\Core\EmailTemplate::render(
                'Welcome to the Community!',
                "You're all set, {$firstName}!",
                $body,
                'Get Started',
                $loginUrl,
                $tenant['name']
            );

            $subject = $creditsAwarded > 0
                ? "Welcome to {$tenantNameSafe} — {$creditsAwarded} time credits are waiting for you!"
                : "Welcome to {$tenantNameSafe} — your account is approved!";

            $result = (new \Nexus\Core\Mailer())->send($user['email'], $subject, $html);

            if ($result) {
                error_log("[AdminUsers] Welcome email sent to {$user['email']} (user #{$user['id']}, credits: {$creditsAwarded})");
            } else {
                error_log("[AdminUsers] Mailer returned false for welcome email to {$user['email']} (user #{$user['id']}) — check SMTP/Gmail config");
            }

            return (bool) $result;
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to send welcome email to user #{$user['id']} ({$user['email']}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create an in-app notification for the approved user.
     * Includes mention of welcome credits if any were awarded.
     *
     * @param array $user User record from the database
     * @param int $creditsAwarded Number of welcome credits awarded (0 = none)
     */
    private function sendApprovalInAppNotification(array $user, int $creditsAwarded = 0): void
    {
        try {
            $tenant = $this->resolveUserTenant($user);

            $message = "Your account has been approved! Welcome to {$tenant['name']}.";
            if ($creditsAwarded > 0) {
                $message .= " You've received {$creditsAwarded} welcome time credit" . ($creditsAwarded !== 1 ? 's' : '') . " to get started.";
            }

            // Use absolute URL — push notifications (FCM/Web Push) require a full URL
            $link = TenantContext::getFrontendUrl() . $tenant['slug_prefix'] . '/dashboard';

            Notification::create(
                (int) $user['id'],
                $message,
                $link,
                'system',
                true,
                $tenant['tenant_id']
            );
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to create approval notification for user #{$user['id']}: " . $e->getMessage());
        }
    }

    /**
     * Send reactivation notification email to a reactivated user.
     *
     * @param array $user User record from the database
     * @return bool Whether the email was sent successfully
     */
    private function sendReactivationNotificationEmail(array $user): bool
    {
        try {
            $tenant = $this->resolveUserTenant($user);
            $firstName = htmlspecialchars($user['first_name'] ?? 'there', ENT_QUOTES, 'UTF-8');
            $tenantNameSafe = htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8');
            $loginUrl = TenantContext::getFrontendUrl() . $tenant['slug_prefix'] . '/login';

            $html = \Nexus\Core\EmailTemplate::render(
                'Account Reactivated',
                "Welcome back, {$firstName}!",
                '<p>Your account on ' . $tenantNameSafe . ' has been reactivated by an administrator.</p>
                 <p>You can now log in and access the platform again.</p>',
                'Log In Now',
                $loginUrl,
                $tenant['name']
            );

            $result = (new \Nexus\Core\Mailer())->send(
                $user['email'],
                "Your account has been reactivated - {$tenantNameSafe}",
                $html
            );

            if ($result) {
                error_log("[AdminUsers] Reactivation email sent to {$user['email']} (user #{$user['id']})");
            } else {
                error_log("[AdminUsers] Mailer returned false for reactivation email to {$user['email']} (user #{$user['id']}) — check SMTP/Gmail config");
            }

            return (bool) $result;
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to send reactivation email to user #{$user['id']} ({$user['email']}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create an in-app notification for a reactivated user.
     *
     * @param array $user User record from the database
     */
    private function sendReactivationInAppNotification(array $user): void
    {
        try {
            $tenant = $this->resolveUserTenant($user);

            // Use absolute URL — push notifications (FCM/Web Push) require a full URL
            $link = TenantContext::getFrontendUrl() . $tenant['slug_prefix'] . '/dashboard';

            Notification::create(
                (int) $user['id'],
                "Your account has been reactivated. Welcome back to {$tenant['name']}!",
                $link,
                'system',
                true,
                $tenant['tenant_id']
            );
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to create reactivation notification for user #{$user['id']}: " . $e->getMessage());
        }
    }

    /**
     * POST /api/v2/admin/users/{id}/suspend
     *
     * Body: { reason? }
     */
    public function suspend(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        if ($id === $adminId) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot suspend your own account', null, 403);
            return;
        }

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only suspend users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        if (!empty($user['is_super_admin'])) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot suspend a super admin', null, 403);
            return;
        }

        // Use the user's own tenant_id for the UPDATE query (safety)
        $userTenantId = (int) $user['tenant_id'];

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Suspended by admin';

        Database::query(
            "UPDATE users SET status = 'suspended' WHERE id = ? AND tenant_id = ?",
            [$id, $userTenantId]
        );

        ActivityLog::log($adminId, 'admin_suspend_user', "Suspended user #{$id}: {$reason}" . ($isSuperAdmin ? " (tenant {$userTenantId})" : ''));
        AuditLogService::logUserSuspended($adminId, $id, $reason);

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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        if ($id === $adminId) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot ban your own account', null, 403);
            return;
        }

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only ban users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        if (!empty($user['is_super_admin'])) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Cannot ban a super admin', null, 403);
            return;
        }

        // Use the user's own tenant_id for the UPDATE query (safety)
        $userTenantId = (int) $user['tenant_id'];

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Banned by admin';

        Database::query(
            "UPDATE users SET status = 'banned' WHERE id = ? AND tenant_id = ?",
            [$id, $userTenantId]
        );

        ActivityLog::log($adminId, 'admin_ban_user', "Banned user #{$id}: {$reason}" . ($isSuperAdmin ? " (tenant {$userTenantId})" : ''));
        AuditLogService::logUserBanned($adminId, $id, $reason);

        $this->respondWithData(['banned' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/users/{id}/reactivate
     */
    public function reactivate(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only reactivate users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Use the user's own tenant_id for the UPDATE query (safety)
        $userTenantId = (int) $user['tenant_id'];

        Database::query(
            "UPDATE users SET status = 'active', is_approved = 1 WHERE id = ? AND tenant_id = ?",
            [$id, $userTenantId]
        );

        ActivityLog::log($adminId, 'admin_reactivate_user', "Reactivated user #{$id} ({$user['email']})" . ($isSuperAdmin ? " (tenant {$userTenantId})" : ''));
        AuditLogService::logUserReactivated($adminId, $id, $user['status'] ?? 'unknown');

        // Notify the reactivated user (email + in-app + push)
        $emailSent = $this->sendReactivationNotificationEmail($user);
        $this->sendReactivationInAppNotification($user);

        $this->respondWithData(['reactivated' => true, 'id' => $id, 'email_sent' => $emailSent]);
    }

    /**
     * POST /api/v2/admin/users/{id}/reset-2fa
     *
     * Body: { reason }
     */
    public function reset2fa(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only reset 2FA for users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Use the user's own tenant_id for all writes (safety)
        $userTenantId = (int) $user['tenant_id'];

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Reset by admin';

        // Clear TOTP settings from proper tables (user_totp_settings, user_backup_codes)
        try {
            Database::query(
                "DELETE FROM user_totp_settings WHERE user_id = ? AND tenant_id = ?",
                [$id, $userTenantId]
            );
            Database::query(
                "DELETE FROM user_backup_codes WHERE user_id = ? AND tenant_id = ?",
                [$id, $userTenantId]
            );
            // Clear the quick-check flag on users table
            Database::query(
                "UPDATE users SET totp_enabled = 0 WHERE id = ? AND tenant_id = ?",
                [$id, $userTenantId]
            );
            // Revoke all trusted devices
            Database::query(
                "UPDATE user_trusted_devices SET is_revoked = 1, revoked_at = NOW(), revoked_reason = 'admin_reset' WHERE user_id = ? AND tenant_id = ?",
                [$id, $userTenantId]
            );
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to reset 2FA for user #{$id}: " . $e->getMessage());
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to reset 2FA', null, 500);
            return;
        }

        ActivityLog::log($adminId, 'admin_reset_2fa', "Reset 2FA for user #{$id}: {$reason}" . ($isSuperAdmin ? " (tenant {$userTenantId})" : ''));
        AuditLogService::log2faReset($adminId, $id, $reason);

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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only add badges for users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
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

            ActivityLog::log($adminId, 'admin_award_badge', "Awarded badge '{$badgeSlug}' to user #{$id}" . ($isSuperAdmin ? " (tenant {$user['tenant_id']})" : ''));

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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only remove badges for users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Use the user's own tenant_id for badge queries (safety)
        $userTenantId = (int) $user['tenant_id'];

        try {
            $badge = Database::query(
                "SELECT * FROM user_badges WHERE id = ? AND user_id = ? AND tenant_id = ?",
                [$badgeId, $id, $userTenantId]
            )->fetch();

            if (!$badge) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Badge not found for this user', null, 404);
                return;
            }

            Database::query("DELETE FROM user_badges WHERE id = ? AND user_id = ? AND tenant_id = ?", [$badgeId, $id, $userTenantId]);

            ActivityLog::log($adminId, 'admin_remove_badge', "Removed badge #{$badgeId} from user #{$id}" . ($isSuperAdmin ? " (tenant {$userTenantId})" : ''));

            $this->respondWithData(['removed' => true, 'user_id' => $id, 'badge_id' => $badgeId]);
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to remove badge: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/v2/admin/users/import
     *
     * Bulk import users from CSV file.
     * CSV format: first_name, last_name, email, phone (optional), role (optional)
     */
    public function import(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No CSV file uploaded or upload error', null, 400);
            return;
        }

        $file = $_FILES['csv_file'];
        $allowedTypes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($file['type'], $allowedTypes)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid file type. Please upload a CSV file.', null, 400);
            return;
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Could not read file', null, 500);
            return;
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Empty CSV file', null, 400);
            return;
        }

        // Normalize headers (lowercase, trim, underscores)
        $header = array_map(function ($h) {
            return strtolower(trim(str_replace([' ', '-'], '_', $h)));
        }, $header);

        // Validate required columns
        $requiredColumns = ['first_name', 'last_name', 'email'];
        $missing = array_diff($requiredColumns, $header);
        if (!empty($missing)) {
            fclose($handle);
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Missing required columns: ' . implode(', ', $missing), null, 400);
            return;
        }

        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];
        $row = 1;
        $defaultRole = $_POST['default_role'] ?? 'member';

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (count($data) !== count($header)) {
                $results['errors'][] = "Row {$row}: Column count mismatch";
                $results['skipped']++;
                continue;
            }

            $record = array_combine($header, $data);
            $email = trim($record['email'] ?? '');
            $firstName = trim($record['first_name'] ?? '');
            $lastName = trim($record['last_name'] ?? '');

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['errors'][] = "Row {$row}: Invalid email '{$email}'";
                $results['skipped']++;
                continue;
            }

            if (empty($firstName) || empty($lastName)) {
                $results['errors'][] = "Row {$row}: First name and last name are required";
                $results['skipped']++;
                continue;
            }

            // Check if user already exists in this tenant
            try {
                $existing = Database::query(
                    "SELECT id FROM users WHERE email = ? AND tenant_id = ?",
                    [$email, $tenantId]
                )->fetch();

                if ($existing) {
                    $results['errors'][] = "Row {$row}: User with email '{$email}' already exists";
                    $results['skipped']++;
                    continue;
                }

                // Create user with random temp password
                $password = bin2hex(random_bytes(16));
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                Database::query(
                    "INSERT INTO users (tenant_id, name, first_name, last_name, email, password_hash, phone, role, status, is_approved, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, NOW())",
                    [
                        $tenantId,
                        trim($firstName . ' ' . $lastName),
                        $firstName,
                        $lastName,
                        $email,
                        $hashedPassword,
                        trim($record['phone'] ?? ''),
                        $record['role'] ?? $defaultRole,
                    ]
                );

                $results['imported']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$row}: " . $e->getMessage();
                $results['skipped']++;
            }
        }

        fclose($handle);

        ActivityLog::log($adminId, 'admin_bulk_import_users', "Bulk imported {$results['imported']} users ({$results['skipped']} skipped)");
        AuditLogService::logBulkImport($adminId, $results['imported'], $results['skipped'], $row - 1);

        $this->respondWithData([
            'imported' => $results['imported'],
            'skipped' => $results['skipped'],
            'errors' => array_slice($results['errors'], 0, 50),
            'total_rows' => $row - 1,
        ]);
    }

    /**
     * GET /api/v2/admin/users/import/template
     *
     * Download CSV template for bulk import.
     */
    public function importTemplate(): void
    {
        $this->requireAdmin();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="user_import_template.csv"');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, ['first_name', 'last_name', 'email', 'phone', 'role']);
        fputcsv($output, ['Jane', 'Doe', 'jane@example.com', '+15551234567', 'member']);
        fputcsv($output, ['John', 'Smith', 'john@example.com', '', 'member']);

        fclose($output);
        exit;
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only impersonate users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
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

        // Warn admin if target user is blocked by registration policy gates
        $userTenantId = (int) $user['tenant_id'];
        $gateBlock = \Nexus\Services\TenantSettingsService::checkLoginGates($user);
        if ($gateBlock) {
            // Allow impersonation but include a warning — admins may need this for testing
            // The gate status is returned so the admin UI can display it
        }

        // Use the user's own tenant_id for the token (so the impersonated session is in the correct tenant)

        try {
            // Generate an access token for the target user with impersonation claim
            $token = \Nexus\Services\TokenService::generateToken($id, $userTenantId, [
                'impersonated_by' => $adminId,
            ]);

            ActivityLog::log($adminId, 'admin_impersonate', "Impersonated user #{$id} ({$user['email']})" . ($isSuperAdmin ? " (tenant {$userTenantId})" : ''));
            AuditLogService::logUserImpersonated($adminId, $id, $user['email']);

            $responseData = [
                'token' => $token,
                'user_id' => $id,
                'user_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            ];
            if ($gateBlock) {
                $responseData['gate_warning'] = $gateBlock['message'];
                $responseData['gate_code'] = $gateBlock['code'];
            }
            $this->respondWithData($responseData);
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to generate impersonation token', null, 500);
        }
    }

    /**
     * PUT /api/v2/admin/users/{id}/super-admin
     *
     * Grant or revoke super admin status for a user.
     * Body: { grant: bool }
     * Only callable by super admins.
     */
    public function setSuperAdmin(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Only super admins can grant/revoke super admin status
        if (!$isSuperAdmin) {
            $this->respondWithError(ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 'Only super admins can manage super admin status', null, 403);
            return;
        }

        // Prevent self-modification
        if ($id === $adminId) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'You cannot modify your own super admin status', null, 422);
            return;
        }

        $grant = (bool) ($this->input('grant', false));

        try {
            // Super admins can manage users across all tenants
            $user = Database::query(
                "SELECT id, email, first_name, last_name, tenant_id FROM users WHERE id = ?",
                [$id]
            )->fetch();

            if (!$user) {
                $this->respondWithError(ApiErrorCodes::NOT_FOUND, 'User not found', null, 404);
                return;
            }

            Database::query(
                "UPDATE users SET is_super_admin = ? WHERE id = ?",
                [$grant ? 1 : 0, $id]
            );

            $action = $grant ? 'grant_super_admin' : 'revoke_super_admin';
            ActivityLog::log($adminId, $action, ($grant ? 'Granted' : 'Revoked') . " super admin for user #{$id}: {$user['email']} (tenant {$user['tenant_id']})");
            if ($grant) {
                AuditLogService::logAdminAction('grant_super_admin', $adminId, $id, ['email' => $user['email']]);
            } else {
                AuditLogService::logSuperAdminRevoked($adminId, $id, $user['email']);
            }

            $this->respondWithData(['id' => $id, 'is_super_admin' => $grant]);
        } catch (\Exception $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update super admin status', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/users/{id}/badges/recheck
     *
     * Recheck all badge criteria for a single user.
     */
    public function recheckBadges(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only recheck badges for users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        try {
            \Nexus\Services\GamificationService::runAllBadgeChecks($id);

            ActivityLog::log($adminId, 'admin_recheck_badges', "Rechecked badges for user #{$id} ({$user['email']})" . ($isSuperAdmin ? " (tenant {$user['tenant_id']})" : ''));

            // Fetch updated badges
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

            $this->respondWithData([
                'rechecked' => true,
                'user_id' => $id,
                'badges' => $badges,
            ]);
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Badge recheck failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/v2/admin/users/{id}/consents
     *
     * Get GDPR consents for a user.
     */
    public function getConsents(int $id): void
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only view consents for users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Use the user's own tenant_id for the GDPR service
        $userTenantId = (int) $user['tenant_id'];

        try {
            $gdprService = new \Nexus\Services\Enterprise\GdprService($userTenantId);
            $consents = $gdprService->getUserConsents($id);

            $formatted = array_map(function ($c) {
                return [
                    'consent_type' => $c['consent_type_slug'] ?? $c['consent_type'] ?? '',
                    'name' => $c['name'] ?? ucwords(str_replace('_', ' ', $c['consent_type_slug'] ?? '')),
                    'description' => $c['description'] ?? null,
                    'category' => $c['category'] ?? null,
                    'is_required' => (bool) ($c['is_required'] ?? false),
                    'consent_given' => (bool) ($c['consent_given'] ?? false),
                    'consent_version' => $c['consent_version'] ?? null,
                    'given_at' => $c['given_at'] ?? null,
                    'withdrawn_at' => $c['withdrawn_at'] ?? null,
                ];
            }, $consents);

            $this->respondWithData($formatted);
        } catch (\Throwable $e) {
            // user_consents table may not exist
            $this->respondWithData([]);
        }
    }

    /**
     * POST /api/v2/admin/users/{id}/password
     *
     * Admin set/change a user's password.
     * Body: { password }
     */
    public function setPassword(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only set passwords for users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Use the user's own tenant_id for the UPDATE query (safety)
        $userTenantId = (int) $user['tenant_id'];

        $input = $this->getAllInput();
        $password = $input['password'] ?? '';

        if (strlen($password) < 8) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Password must be at least 8 characters', 'password', 422);
            return;
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        Database::query(
            "UPDATE users SET password_hash = ? WHERE id = ? AND tenant_id = ?",
            [$hashed, $id, $userTenantId]
        );

        ActivityLog::log($adminId, 'admin_set_password', "Admin set password for user #{$id} ({$user['email']})" . ($isSuperAdmin ? " (tenant {$userTenantId})" : ''));
        AuditLogService::logAdminAction('admin_set_password', $adminId, $id, ['email' => $user['email']]);

        $this->respondWithData(['password_set' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/users/{id}/send-password-reset
     *
     * Send password reset email to a user.
     */
    public function sendPasswordReset(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only send password resets for users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Use the user's own tenant_id for the UPDATE query (safety)
        $userTenantId = (int) $user['tenant_id'];

        try {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            Database::query(
                "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ? AND tenant_id = ?",
                [$token, $expiry, $id, $userTenantId]
            );

            $tenant = $this->resolveUserTenant($user);
            $tenantNameSafe = htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8');
            $resetLink = TenantContext::getFrontendUrl() . $tenant['slug_prefix'] . "/reset-password?token={$token}&email=" . urlencode($user['email']);

            $html = \Nexus\Core\EmailTemplate::render(
                "Password Reset",
                "Reset your password for {$tenantNameSafe}",
                "<p>Hello <strong>" . htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                <p>An administrator has requested a password reset for your account.</p>
                <p>Click the button below to set a new password. This link expires in 24 hours.</p>",
                "Reset Password",
                $resetLink,
                $tenant['name']
            );

            $mailer = new \Nexus\Core\Mailer();
            $mailer->send($user['email'], "Password Reset - {$tenantNameSafe}", $html);

            ActivityLog::log($adminId, 'admin_send_password_reset', "Sent password reset email to user #{$id} ({$user['email']})" . ($isSuperAdmin ? " (tenant {$userTenantId})" : ''));

            $this->respondWithData(['sent' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to send password reset email: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/v2/admin/users/{id}/send-welcome-email
     *
     * Resend the welcome email to a user.
     */
    public function sendWelcomeEmail(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $user = User::findById($id, !$isSuperAdmin);
        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        // Regular admins can only send welcome emails to users in their own tenant
        if (!$isSuperAdmin && $user['tenant_id'] != $tenantId) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
            return;
        }

        try {
            // Resolve tenant from the USER's tenant_id (not admin's context)
            $resolvedTenant = $this->resolveUserTenant($user);
            $userTenantId = $resolvedTenant['tenant_id'];
            $tenantName = $resolvedTenant['name'];
            $tenantNameSafe = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');

            // Read tenant configuration for custom welcome email content
            $tenantRow = Database::query("SELECT configuration FROM tenants WHERE id = ?", [$userTenantId])->fetch();
            $config = json_decode($tenantRow['configuration'] ?? '{}', true);
            $welcomeConfig = $config['welcome_email'] ?? [];

            $subject = !empty($welcomeConfig['subject']) ? $welcomeConfig['subject'] : "Welcome to {$tenantNameSafe}";

            $firstName = htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8');

            if (!empty($welcomeConfig['body'])) {
                $mainMessage = $welcomeConfig['body'];
            } else {
                $mainMessage = "<p>Hello <strong>{$firstName}</strong>,</p>
                <p>Welcome to {$tenantNameSafe}! Your account is ready to use.</p>
                <p>Log in to start connecting with your community, browse available services, and offer your own skills.</p>";
            }

            $loginLink = TenantContext::getFrontendUrl() . $resolvedTenant['slug_prefix'] . "/login";

            if (stripos($mainMessage, '<!DOCTYPE') !== false || stripos($mainMessage, '<html') !== false) {
                $html = $mainMessage;
            } else {
                $html = \Nexus\Core\EmailTemplate::render(
                    "Welcome!",
                    "You are a member of {$tenantNameSafe}",
                    $mainMessage,
                    "Login & Get Started",
                    $loginLink,
                    $tenantName
                );
            }

            $mailer = new \Nexus\Core\Mailer();
            $mailer->send($user['email'], $subject, $html);

            ActivityLog::log($adminId, 'admin_resend_welcome', "Resent welcome email to user #{$id} ({$user['email']})" . ($isSuperAdmin ? " (tenant {$userTenantId})" : ''));

            $this->respondWithData(['sent' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to send welcome email: ' . $e->getMessage(), null, 500);
        }
    }
}
