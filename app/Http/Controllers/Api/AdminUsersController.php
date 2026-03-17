<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\User;
use Nexus\Models\Notification;
use Nexus\Models\ActivityLog;
use Nexus\Services\AuditLogService;
use Nexus\Services\TenantSettingsService;

/**
 * AdminUsersController — Admin user management (list, view, create, update, approve, suspend, ban, etc.).
 *
 * All methods require admin authentication.
 * Methods involving email sending are kept as delegation to legacy controller.
 */
class AdminUsersController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    // =========================================================================
    // List & Show
    // =========================================================================

    /** GET /api/v2/admin/users */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = ($page - 1) * $limit;
        $status = $this->query('status');
        $search = $this->query('search');
        $role = $this->query('role');
        $sort = $this->query('sort', 'created_at');
        $order = strtoupper($this->query('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $allowedSorts = ['name', 'email', 'role', 'created_at', 'balance', 'status'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $conditions = ['u.tenant_id = ?'];
        $params = [$tenantId];

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

        if ($role) {
            $conditions[] = 'u.role = ?';
            $params[] = $role;
        }

        if ($search) {
            $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $where = implode(' AND ', $conditions);

        $sortColumnMap = [
            'name' => 'name',
            'email' => 'u.email',
            'role' => 'u.role',
            'created_at' => 'u.created_at',
            'balance' => 'COALESCE(u.balance, 0)',
            'status' => "CASE WHEN u.is_approved = 0 THEN 'pending' WHEN u.status = 'suspended' THEN 'suspended' WHEN u.status = 'banned' THEN 'banned' ELSE 'active' END",
        ];
        $sortColumn = $sortColumnMap[$sort] ?? 'u.created_at';

        $total = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM users u WHERE {$where}", $params)->cnt;

        $users = DB::select(
            "SELECT u.id, u.first_name, u.last_name,
                CASE
                    WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                        THEN u.organization_name
                    ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
                END as name,
                u.email, u.avatar_url, u.location, u.role, u.is_approved, u.is_super_admin, u.is_tenant_super_admin,
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
        );

        $formatted = array_map(function ($row) {
            $status = 'active';
            if (!$row->is_approved) {
                $status = 'pending';
            } elseif ($row->status === 'suspended') {
                $status = 'suspended';
            } elseif ($row->status === 'banned') {
                $status = 'banned';
            }

            return [
                'id' => (int) $row->id,
                'name' => trim($row->name),
                'first_name' => $row->first_name ?? '',
                'last_name' => $row->last_name ?? '',
                'email' => $row->email,
                'avatar_url' => $row->avatar_url ?? null,
                'location' => $row->location ?? null,
                'role' => $row->role ?? 'member',
                'status' => $status,
                'is_super_admin' => (bool) ($row->is_super_admin ?? false),
                'is_tenant_super_admin' => (bool) ($row->is_tenant_super_admin ?? false),
                'balance' => (float) ($row->balance ?? 0),
                'listing_count' => (int) ($row->listing_count ?? 0),
                'profile_type' => $row->profile_type ?? 'individual',
                'tenant_id' => (int) $row->tenant_id,
                'tenant_name' => $row->tenant_name ?? 'Unknown',
                'has_2fa_enabled' => false,
                'created_at' => $row->created_at,
                'last_active_at' => $row->last_active_at ?? null,
            ];
        }, $users);

        return $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /** GET /api/v2/admin/users/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $user = DB::selectOne(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url, u.location, u.bio, u.tagline, u.phone,
                    u.role, u.status, u.is_approved, u.is_super_admin, u.is_god, u.is_tenant_super_admin, u.balance, u.profile_type,
                    u.organization_name, u.vetting_status, u.insurance_status, u.created_at, u.last_active_at,
                    u.email_verified_at, u.is_verified,
                    u.tenant_id, t.name as tenant_name
             FROM users u
             LEFT JOIN tenants t ON u.tenant_id = t.id
             WHERE u.id = ? AND u.tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$user) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        $status = 'active';
        if (!$user->is_approved) {
            $status = 'pending';
        } elseif ($user->status === 'suspended') {
            $status = 'suspended';
        } elseif ($user->status === 'banned') {
            $status = 'banned';
        }

        $badges = [];
        try {
            $badgeRows = DB::select(
                "SELECT ub.id, ub.badge_key, ub.awarded_at, ub.badge_name, ub.badge_description, ub.badge_icon
                 FROM user_badges ub WHERE ub.user_id = ? ORDER BY ub.awarded_at DESC",
                [$id]
            );
            $badges = array_map(fn($b) => [
                'id' => (int) $b->id,
                'name' => $b->badge_name ?? $b->badge_key ?? '',
                'slug' => $b->badge_key ?? '',
                'description' => $b->badge_description ?? '',
                'icon' => $b->badge_icon ?? null,
                'awarded_at' => $b->awarded_at ?? '',
            ], $badgeRows);
        } catch (\Throwable $e) {}

        return $this->respondWithData([
            'id' => (int) $user->id,
            'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
            'email' => $user->email,
            'avatar_url' => $user->avatar_url ?? null,
            'location' => $user->location ?? null,
            'bio' => $user->bio ?? null,
            'tagline' => $user->tagline ?? null,
            'phone' => $user->phone ?? null,
            'role' => $user->role ?? 'member',
            'status' => $status,
            'is_super_admin' => (bool) ($user->is_super_admin ?? false),
            'is_god' => (bool) ($user->is_god ?? false),
            'is_tenant_super_admin' => (bool) ($user->is_tenant_super_admin ?? false),
            'is_admin' => in_array($user->role ?? '', ['admin', 'tenant_admin']),
            'balance' => (float) ($user->balance ?? 0),
            'profile_type' => $user->profile_type ?? 'individual',
            'organization_name' => $user->organization_name ?? null,
            'tenant_id' => (int) $user->tenant_id,
            'tenant_name' => $user->tenant_name ?? 'Unknown',
            'badges' => $badges,
            'is_approved' => (bool) ($user->is_approved ?? false),
            'email_verified_at' => $user->email_verified_at ?? null,
            'is_verified' => (bool) ($user->is_verified ?? false),
            'vetting_status' => $user->vetting_status ?? 'none',
            'insurance_status' => $user->insurance_status ?? 'none',
            'created_at' => $user->created_at,
            'last_active_at' => $user->last_active_at ?? null,
        ]);
    }

    // =========================================================================
    // Update
    // =========================================================================

    /** PUT /api/v2/admin/users/{id} */
    public function update(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        $input = $this->getAllInput();
        $updates = [];
        $params = [];

        $fieldMap = ['first_name', 'last_name', 'email', 'role', 'location', 'phone', 'bio', 'tagline', 'organization_name'];
        foreach ($fieldMap as $field) {
            if (isset($input[$field])) {
                $value = is_string($input[$field]) ? trim($input[$field]) : $input[$field];
                if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $this->respondWithError('VALIDATION_ERROR', 'Invalid email', 'email', 422);
                }
                $updates[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if (isset($input['profile_type']) && in_array($input['profile_type'], ['individual', 'organisation'])) {
            $updates[] = 'profile_type = ?';
            $params[] = $input['profile_type'];
        }

        if (isset($input['status']) && in_array($input['status'], ['active', 'suspended', 'banned'])) {
            $updates[] = 'status = ?';
            $params[] = $input['status'];
            if ($input['status'] === 'active') {
                $updates[] = 'is_approved = 1';
            }
        }

        if (empty($updates)) {
            return $this->respondWithError('VALIDATION_ERROR', 'No fields to update', null, 422);
        }

        $params[] = $id;
        $params[] = $tenantId;

        DB::update("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?", $params);

        ActivityLog::log($adminId, 'admin_update_user', "Updated user #{$id}");
        AuditLogService::logUserUpdated($adminId, $id, array_keys($input));

        if (isset($input['role']) && ($user['role'] ?? 'member') !== $input['role']) {
            AuditLogService::logAdminRoleChanged($adminId, $id, $user['role'] ?? 'member', $input['role']);
        }

        return $this->show($id);
    }

    // =========================================================================
    // Create (email-sending — delegate to legacy)
    // =========================================================================

    /** POST /api/v2/admin/users */
    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'store');
    }

    // =========================================================================
    // Approve
    // =========================================================================

    /** POST /api/v2/admin/users/{id}/approve */
    public function approve(int $id): JsonResponse
    {
        // Approve involves welcome email + credits — delegate to legacy
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'approve', [$id]);
    }

    // =========================================================================
    // Status Changes
    // =========================================================================

    /** POST /api/v2/admin/users/{id}/suspend */
    public function suspend($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        if ($id === $adminId) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', 'Cannot suspend your own account', null, 403);
        }

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        if (!empty($user['is_super_admin']) || !empty($user['is_tenant_super_admin'])) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', 'Cannot suspend a super admin', null, 403);
        }

        $reason = $this->input('reason', 'Suspended by admin');

        DB::update("UPDATE users SET status = 'suspended' WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        ActivityLog::log($adminId, 'admin_suspend_user', "Suspended user #{$id}: {$reason}");
        AuditLogService::logUserSuspended($adminId, $id, $reason);

        return $this->respondWithData(['suspended' => true, 'id' => $id]);
    }

    /** POST /api/v2/admin/users/{id}/ban */
    public function ban($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        if ($id === $adminId) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', 'Cannot ban your own account', null, 403);
        }

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        if (!empty($user['is_super_admin']) || !empty($user['is_tenant_super_admin'])) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', 'Cannot ban a super admin', null, 403);
        }

        $reason = $this->input('reason', 'Banned by admin');

        DB::update("UPDATE users SET status = 'banned' WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        ActivityLog::log($adminId, 'admin_ban_user', "Banned user #{$id}: {$reason}");
        AuditLogService::logUserBanned($adminId, $id, $reason);

        return $this->respondWithData(['banned' => true, 'id' => $id]);
    }

    /** POST /api/v2/admin/users/{id}/reactivate */
    public function reactivate($id): JsonResponse
    {
        // Reactivate involves email notification — delegate to legacy
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'reactivate', [(int) $id]);
    }

    // =========================================================================
    // Delete
    // =========================================================================

    /** DELETE /api/v2/admin/users/{id} */
    public function destroy($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        if ($id === $adminId) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', 'Cannot delete your own account', null, 403);
        }

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        if (!empty($user['is_super_admin']) || !empty($user['is_tenant_super_admin'])) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', 'Cannot delete a super admin', null, 403);
        }

        DB::delete("DELETE FROM users WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        ActivityLog::log($adminId, 'admin_delete_user', "Deleted user #{$id} ({$user['email']})");
        AuditLogService::logUserDeleted($adminId, $id, $user['email']);

        return $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    // =========================================================================
    // 2FA, Badges, Password, Consents
    // =========================================================================

    /** POST /api/v2/admin/users/{id}/reset-2fa */
    public function reset2fa($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        $reason = $this->input('reason', 'Reset by admin');

        try {
            DB::delete("DELETE FROM user_totp_settings WHERE user_id = ? AND tenant_id = ?", [$id, $tenantId]);
            DB::delete("DELETE FROM user_backup_codes WHERE user_id = ? AND tenant_id = ?", [$id, $tenantId]);
            DB::update("UPDATE users SET totp_enabled = 0 WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            DB::update("UPDATE user_trusted_devices SET is_revoked = 1, revoked_at = NOW(), revoked_reason = 'admin_reset' WHERE user_id = ? AND tenant_id = ?", [$id, $tenantId]);
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to reset 2FA', null, 500);
        }

        ActivityLog::log($adminId, 'admin_reset_2fa', "Reset 2FA for user #{$id}: {$reason}");
        AuditLogService::log2faReset($adminId, $id, $reason);

        return $this->respondWithData(['reset' => true, 'id' => $id]);
    }

    /** POST /api/v2/admin/users/{id}/badges */
    public function addBadge($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        $badgeSlug = trim($this->input('badge_slug', ''));
        if (empty($badgeSlug)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Badge slug is required', 'badge_slug', 422);
        }

        try {
            \Nexus\Services\GamificationService::awardBadgeByKey($id, $badgeSlug);
            ActivityLog::log($adminId, 'admin_award_badge', "Awarded badge '{$badgeSlug}' to user #{$id}");
            return $this->respondWithData(['awarded' => true, 'user_id' => $id, 'badge_slug' => $badgeSlug], null, 201);
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to award badge: ' . $e->getMessage(), null, 500);
        }
    }

    /** DELETE /api/v2/admin/users/{id}/badges/{badgeId} */
    public function removeBadge($id, $badgeId): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;
        $badgeId = (int) $badgeId;

        $badge = DB::selectOne(
            "SELECT * FROM user_badges WHERE id = ? AND user_id = ? AND tenant_id = ?",
            [$badgeId, $id, $tenantId]
        );

        if (!$badge) {
            return $this->respondWithError('NOT_FOUND', 'Badge not found for this user', null, 404);
        }

        DB::delete("DELETE FROM user_badges WHERE id = ? AND user_id = ? AND tenant_id = ?", [$badgeId, $id, $tenantId]);
        ActivityLog::log($adminId, 'admin_remove_badge', "Removed badge #{$badgeId} from user #{$id}");

        return $this->respondWithData(['removed' => true, 'user_id' => $id, 'badge_id' => $badgeId]);
    }

    /** POST /api/v2/admin/users/{id}/badges/recheck */
    public function recheckBadges($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        try {
            \Nexus\Services\GamificationService::runAllBadgeChecks($id);
            ActivityLog::log($adminId, 'admin_recheck_badges', "Rechecked badges for user #{$id}");

            $badgeRows = DB::select(
                "SELECT ub.id, ub.badge_key, ub.awarded_at, ub.badge_name, ub.badge_description, ub.badge_icon
                 FROM user_badges ub WHERE ub.user_id = ? ORDER BY ub.awarded_at DESC",
                [$id]
            );

            $badges = array_map(fn($b) => [
                'id' => (int) $b->id,
                'name' => $b->badge_name ?? $b->badge_key ?? '',
                'slug' => $b->badge_key ?? '',
                'description' => $b->badge_description ?? '',
                'icon' => $b->badge_icon ?? null,
                'awarded_at' => $b->awarded_at ?? '',
            ], $badgeRows);

            return $this->respondWithData(['rechecked' => true, 'user_id' => $id, 'badges' => $badges]);
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', 'Badge recheck failed: ' . $e->getMessage(), null, 500);
        }
    }

    /** GET /api/v2/admin/users/{id}/consents */
    public function getConsents($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        try {
            $gdprService = new \Nexus\Services\Enterprise\GdprService($tenantId);
            $consents = $gdprService->getUserConsents($id);

            $formatted = array_map(fn($c) => [
                'consent_type' => $c['consent_type_slug'] ?? $c['consent_type'] ?? '',
                'name' => $c['name'] ?? ucwords(str_replace('_', ' ', $c['consent_type_slug'] ?? '')),
                'description' => $c['description'] ?? null,
                'category' => $c['category'] ?? null,
                'is_required' => (bool) ($c['is_required'] ?? false),
                'consent_given' => (bool) ($c['consent_given'] ?? false),
                'consent_version' => $c['consent_version'] ?? null,
                'given_at' => $c['given_at'] ?? null,
                'withdrawn_at' => $c['withdrawn_at'] ?? null,
            ], $consents);

            return $this->respondWithData($formatted);
        } catch (\Throwable $e) {
            return $this->respondWithData([]);
        }
    }

    /** POST /api/v2/admin/users/{id}/password */
    public function setPassword($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        $password = $this->input('password', '');
        if (strlen($password) < 8) {
            return $this->respondWithError('VALIDATION_ERROR', 'Password must be at least 8 characters', 'password', 422);
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        DB::update("UPDATE users SET password_hash = ? WHERE id = ? AND tenant_id = ?", [$hashed, $id, $tenantId]);

        ActivityLog::log($adminId, 'admin_set_password', "Admin set password for user #{$id} ({$user['email']})");
        AuditLogService::logAdminAction('admin_set_password', $adminId, $id, ['email' => $user['email']]);

        return $this->respondWithData(['password_set' => true, 'id' => $id]);
    }

    // =========================================================================
    // Impersonate & Super Admin (complex logic — use legacy services)
    // =========================================================================

    /** POST /api/v2/admin/users/{id}/impersonate */
    public function impersonate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'impersonate', [(int) $id]);
    }

    /** PUT /api/v2/admin/users/{id}/super-admin */
    public function setSuperAdmin($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'setSuperAdmin', [(int) $id]);
    }

    /** PUT /api/v2/admin/users/{id}/global-super-admin */
    public function setGlobalSuperAdmin($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'setGlobalSuperAdmin', [(int) $id]);
    }

    // =========================================================================
    // Email-sending methods (delegation kept for Mailer/email template logic)
    // =========================================================================

    /** POST /api/v2/admin/users/{id}/send-password-reset */
    public function sendPasswordReset($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'sendPasswordReset', [(int) $id]);
    }

    /** POST /api/v2/admin/users/{id}/send-welcome-email */
    public function sendWelcomeEmail($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'sendWelcomeEmail', [(int) $id]);
    }

    // =========================================================================
    // Import (file upload — delegation kept)
    // =========================================================================

    /** POST /api/v2/admin/users/import */
    public function import(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'import');
    }

    /** GET /api/v2/admin/users/import/template */
    public function importTemplate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'importTemplate');
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
