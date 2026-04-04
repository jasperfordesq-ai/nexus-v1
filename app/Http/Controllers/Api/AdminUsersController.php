<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Models\User;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Services\AuditLogService;
use App\Services\Enterprise\GdprService;
use App\Services\GamificationService;
use App\Services\TenantSettingsService;
use App\Services\TokenService;
use Illuminate\Support\Facades\Log;

/**
 * AdminUsersController — Admin user management (list, view, create, update, approve, suspend, ban, etc.).
 *
 * All methods require admin authentication.
 * Fully converted to direct service/DB calls — no legacy delegation.
 */
class AdminUsersController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GamificationService $gamificationService,
        private readonly GdprService $gdprService,
        private readonly TenantSettingsService $tenantSettingsService,
        private readonly TokenService $tokenService,
    ) {}

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
                case 'never_logged_in':
                    $conditions[] = 'u.is_approved = 1 AND u.last_login_at IS NULL';
                    break;
                case 'onboarding_incomplete':
                    $conditions[] = 'u.is_approved = 1 AND (u.onboarding_completed = 0 OR u.onboarding_completed IS NULL)';
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
                u.status, u.created_at, u.last_active_at, u.last_login_at, u.onboarding_completed, u.profile_type, u.organization_name,
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
                'last_login_at' => $row->last_login_at ?? null,
                'onboarding_completed' => (bool) ($row->onboarding_completed ?? false),
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
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
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
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        $input = $this->getAllInput();
        $updates = [];
        $params = [];

        // SECURITY: Restrict role values to prevent privilege escalation (SEC-007)
        $allowedRoles = ['member', 'admin', 'broker', 'moderator', 'newsletter_admin'];
        // Only super admins can assign elevated roles
        if (isset($input['role']) && in_array($input['role'], ['tenant_admin', 'super_admin', 'god'], true)) {
            if (!$this->isCallerSuperAdmin($adminId)) {
                return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.only_super_admins_assign_roles'), null, 403);
            }
            // Super admins can also assign tenant_admin
            $allowedRoles = array_merge($allowedRoles, ['tenant_admin']);
            // super_admin and god roles should only be managed via dedicated endpoints
            if (in_array($input['role'], ['super_admin', 'god'], true)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.use_super_admin_endpoints'), null, 422);
            }
        }

        $fieldMap = ['first_name', 'last_name', 'email', 'role', 'location', 'phone', 'bio', 'tagline', 'organization_name'];
        foreach ($fieldMap as $field) {
            if (isset($input[$field])) {
                $value = is_string($input[$field]) ? trim($input[$field]) : $input[$field];
                if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_email'), 'email', 422);
                }
                // SECURITY: Validate role against allowed values
                if ($field === 'role' && !in_array($value, $allowedRoles, true)) {
                    return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_role'), 'role', 422);
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
            return $this->respondWithError('VALIDATION_ERROR', __('api.no_fields_to_update'), null, 422);
        }

        // Capture old status before the update for change detection
        $oldStatus = $user['status'] ?? 'active';

        $params[] = $id;
        $params[] = $tenantId;

        DB::update("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?", $params);

        ActivityLog::log($adminId, 'admin_update_user', "Updated user #{$id}");
        AuditLogService::logUserUpdated($adminId, $id, array_keys($input));

        if (isset($input['role']) && ($user['role'] ?? 'member') !== $input['role']) {
            AuditLogService::logAdminRoleChanged($adminId, $id, $user['role'] ?? 'member', $input['role']);
        }

        // Notify user when status changes to suspended/banned via the generic update path
        // (The dedicated suspend()/ban() endpoints have their own richer notifications)
        if (isset($input['status']) && $input['status'] !== $oldStatus) {
            try {
                if ($input['status'] === 'suspended') {
                    Notification::createNotification(
                        $id,
                        'Your account has been suspended. Contact support if you believe this is an error.',
                        null,
                        'system',
                        true
                    );
                } elseif ($input['status'] === 'banned') {
                    Notification::createNotification(
                        $id,
                        'Your account has been banned. Contact support if you believe this is an error.',
                        null,
                        'system',
                        true
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("AdminUsersController::update status notification failed for user #{$id}: " . $e->getMessage());
            }
        }

        return $this->show($id);
    }

    // =========================================================================
    // Create
    // =========================================================================

    /** POST /api/v2/admin/users */
    public function store(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $input = $this->getAllInput();

        $firstName = trim($input['first_name'] ?? '');
        $lastName = trim($input['last_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'member';
        $location = trim($input['location'] ?? '');

        // SECURITY: Restrict role to prevent privilege escalation via user creation (SEC-009)
        $allowedRoles = ['member', 'admin', 'broker', 'moderator', 'newsletter_admin'];
        // Only super admins can assign elevated roles like tenant_admin
        if ($role === 'tenant_admin') {
            if (!$this->isCallerSuperAdmin($adminId)) {
                return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.only_super_admins_assign_roles'), null, 403);
            }
            $allowedRoles[] = 'tenant_admin';
        }
        if (!in_array($role, $allowedRoles, true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_role_allowed', ['roles' => implode(', ', $allowedRoles)]), 'role', 422);
        }

        // Auto-generate password if not provided
        if (empty($password)) {
            $password = bin2hex(random_bytes(12));
        }

        // Validation
        $errors = [];
        if (empty($firstName)) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.first_name_required'), 'field' => 'first_name'];
        }
        if (empty($lastName)) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.last_name_required'), 'field' => 'last_name'];
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.valid_email_required'), 'field' => 'email'];
        }
        if (strlen($password) < 8) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.password_min_length'), 'field' => 'password'];
        }

        if (!empty($errors)) {
            return $this->respondWithErrors($errors, 422);
        }

        // Check duplicate email
        $existing = User::findByEmail($email);
        if ($existing) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.email_already_exists'), 'email', 422);
        }

        // Create user via createWithTenant (direct DB insert, not Eloquent::create)
        $newUserId = User::createWithTenant([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $password,
            'location' => $location ?: null,
            'role' => $role,
            'is_approved' => 1,
        ], $tenantId);

        if (!$newUserId) {
            return $this->respondWithError('SERVER_ERROR', __('api.user_created_failed'), null, 500);
        }

        ActivityLog::log($adminId, 'admin_create_user', "Created user: {$email}");
        AuditLogService::logUserCreated($adminId, $newUserId, $email);

        // Record GDPR consents (admin-created accounts)
        try {
            $consentText = "Account created by administrator. User agrees to Terms of Service and Privacy Policy upon first login.";
            $consentVersion = '1.0';
            $this->gdprService->recordConsent($newUserId, 'terms_of_service', true, $consentText, $consentVersion);
            $this->gdprService->recordConsent($newUserId, 'privacy_policy', true, $consentText, $consentVersion);
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

                $html = \App\Core\EmailTemplate::render(
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

                $mailer = \App\Core\Mailer::forCurrentTenant();
                $mailer->send($email, "Your account on {$tenantName}", $html);
            } catch (\Throwable $e) {
                error_log("Welcome email failed for admin-created user: " . $e->getMessage());
            }
        }

        return $this->respondWithData([
            'id' => $newUserId,
            'name' => trim($firstName . ' ' . $lastName),
            'email' => $email,
            'role' => $role,
            'status' => 'active',
        ], null, 201);
    }

    // =========================================================================
    // Approve
    // =========================================================================

    /** POST /api/v2/admin/users/{id}/approve */
    public function approve(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        // Idempotency: prevent double-approval (and double welcome credits)
        if (!empty($user['is_approved'])) {
            return $this->respondWithData(['approved' => true, 'id' => $id, 'already_approved' => true]);
        }

        User::updateAdminFields($id, [
            'role' => $user['role'] ?? 'member',
            'is_approved' => 1,
            'tenant_id' => (int) $user['tenant_id'],
        ]);

        ActivityLog::log($adminId, 'admin_approve_user', "Approved user #{$id} ({$user['email']})");
        AuditLogService::logUserApproved($adminId, $id, $user['email']);

        // Grant welcome credits + send combined welcome email + in-app notification
        $creditsAwarded = $this->grantWelcomeCredits($user, $adminId);
        $emailSent = $this->sendApprovalWelcomeEmail($user, $creditsAwarded);
        $this->sendApprovalInAppNotification($user, $creditsAwarded);

        return $this->respondWithData([
            'approved' => true,
            'id' => $id,
            'email_sent' => $emailSent,
            'welcome_credits' => $creditsAwarded,
        ]);
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
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.cannot_suspend_own_account'), null, 403);
        }

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        if (!empty($user['is_super_admin']) || !empty($user['is_tenant_super_admin'])) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.cannot_suspend_super_admin'), null, 403);
        }

        $reason = $this->input('reason', 'Suspended by admin');

        DB::update("UPDATE users SET status = 'suspended' WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        ActivityLog::log($adminId, 'admin_suspend_user', "Suspended user #{$id}: {$reason}");
        AuditLogService::logUserSuspended($adminId, $id, $reason);

        // Notify the suspended user (bell + email — they may not be able to log in)
        try {
            Notification::createNotification(
                $id,
                'Your account has been suspended. Contact support if you believe this is an error.',
                null,
                'system',
                true
            );
        } catch (\Throwable $e) {
            Log::warning("[AdminUsers] Failed to create suspension bell notification for user #{$id}: " . $e->getMessage());
        }

        try {
            $tenant = $this->resolveUserTenant($user);
            $firstName = htmlspecialchars($user['first_name'] ?? 'there', ENT_QUOTES, 'UTF-8');
            $tenantNameSafe = htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8');

            $html = \App\Core\EmailTemplate::render(
                'Account Suspended',
                "Important notice, {$firstName}",
                '<p>Your account on <strong>' . $tenantNameSafe . '</strong> has been suspended.</p>
                 <p>If you believe this is an error, please contact your community administrator for assistance.</p>',
                null,
                null,
                $tenant['name']
            );

            (\App\Core\Mailer::forCurrentTenant())->send(
                $user['email'],
                "Your account has been suspended - {$tenantNameSafe}",
                $html
            );
        } catch (\Throwable $e) {
            Log::warning("[AdminUsers] Failed to send suspension email to user #{$id}: " . $e->getMessage());
        }

        return $this->respondWithData(['suspended' => true, 'id' => $id]);
    }

    /** POST /api/v2/admin/users/{id}/ban */
    public function ban($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        if ($id === $adminId) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.cannot_ban_own_account'), null, 403);
        }

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        if (!empty($user['is_super_admin']) || !empty($user['is_tenant_super_admin'])) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.cannot_ban_super_admin'), null, 403);
        }

        $reason = $this->input('reason', 'Banned by admin');

        DB::update("UPDATE users SET status = 'banned' WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        ActivityLog::log($adminId, 'admin_ban_user', "Banned user #{$id}: {$reason}");
        AuditLogService::logUserBanned($adminId, $id, $reason);

        // Notify the banned user (email only — they can't log in at all)
        try {
            $tenant = $this->resolveUserTenant($user);
            $firstName = htmlspecialchars($user['first_name'] ?? 'there', ENT_QUOTES, 'UTF-8');
            $tenantNameSafe = htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8');

            $html = \App\Core\EmailTemplate::render(
                'Account Banned',
                "Important notice, {$firstName}",
                '<p>Your account on <strong>' . $tenantNameSafe . '</strong> has been permanently banned.</p>
                 <p>If you believe this is an error, please contact your community administrator.</p>',
                null,
                null,
                $tenant['name']
            );

            (\App\Core\Mailer::forCurrentTenant())->send(
                $user['email'],
                "Your account has been banned - {$tenantNameSafe}",
                $html
            );
        } catch (\Throwable $e) {
            Log::warning("[AdminUsers] Failed to send ban email to user #{$id}: " . $e->getMessage());
        }

        return $this->respondWithData(['banned' => true, 'id' => $id]);
    }

    /** POST /api/v2/admin/users/{id}/reactivate */
    public function reactivate($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        DB::update("UPDATE users SET status = 'active', is_approved = 1 WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        ActivityLog::log($adminId, 'admin_reactivate_user', "Reactivated user #{$id} ({$user['email']})");
        AuditLogService::logUserReactivated($adminId, $id, $user['status'] ?? 'unknown');

        // Notify the reactivated user (email + in-app)
        $emailSent = $this->sendReactivationNotificationEmail($user);
        $this->sendReactivationInAppNotification($user);

        return $this->respondWithData(['reactivated' => true, 'id' => $id, 'email_sent' => $emailSent]);
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
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.cannot_delete_own_account'), null, 403);
        }

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        if (!empty($user['is_super_admin']) || !empty($user['is_tenant_super_admin'])) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.cannot_delete_super_admin'), null, 403);
        }

        // Send deletion email BEFORE the actual deletion (user record must still exist)
        try {
            $tenant = $this->resolveUserTenant($user);
            $firstName = htmlspecialchars($user['first_name'] ?? 'there', ENT_QUOTES, 'UTF-8');
            $tenantNameSafe = htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8');

            $html = \App\Core\EmailTemplate::render(
                'Account Deleted',
                "Important notice, {$firstName}",
                '<p>Your account on <strong>' . $tenantNameSafe . '</strong> has been scheduled for deletion.</p>
                 <p>If you believe this is an error, please contact your community administrator as soon as possible.</p>',
                null,
                null,
                $tenant['name']
            );

            (\App\Core\Mailer::forCurrentTenant())->send(
                $user['email'],
                "Your account has been scheduled for deletion - {$tenantNameSafe}",
                $html
            );
        } catch (\Throwable $e) {
            Log::warning("[AdminUsers] Failed to send deletion email to user #{$id}: " . $e->getMessage());
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
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        $reason = $this->input('reason', 'Reset by admin');

        try {
            DB::delete("DELETE FROM user_totp_settings WHERE user_id = ? AND tenant_id = ?", [$id, $tenantId]);
            DB::delete("DELETE FROM user_backup_codes WHERE user_id = ? AND tenant_id = ?", [$id, $tenantId]);
            DB::update("UPDATE users SET totp_enabled = 0 WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            DB::update("UPDATE user_trusted_devices SET is_revoked = 1, revoked_at = NOW(), revoked_reason = 'admin_reset' WHERE user_id = ? AND tenant_id = ?", [$id, $tenantId]);
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.update_failed', ['resource' => '2FA']), null, 500);
        }

        ActivityLog::log($adminId, 'admin_reset_2fa', "Reset 2FA for user #{$id}: {$reason}");
        AuditLogService::log2faReset($adminId, $id, $reason);

        // Notify the user (bell + email — security-critical action)
        try {
            Notification::createNotification(
                $id,
                'Your two-factor authentication has been reset by an administrator. If you did not request this, please contact support immediately.',
                '/settings/security',
                'security',
                true
            );
        } catch (\Throwable $e) {
            Log::warning("[AdminUsers] Failed to create 2FA reset bell notification for user #{$id}: " . $e->getMessage());
        }

        try {
            $tenant = $this->resolveUserTenant($user);
            $firstName = htmlspecialchars($user['first_name'] ?? 'there', ENT_QUOTES, 'UTF-8');
            $tenantNameSafe = htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8');
            $loginUrl = TenantContext::getFrontendUrl() . $tenant['slug_prefix'] . '/login';

            $html = \App\Core\EmailTemplate::render(
                'Two-Factor Authentication Reset',
                "Security notice, {$firstName}",
                '<p>Your two-factor authentication on <strong>' . $tenantNameSafe . '</strong> has been reset by an administrator.</p>
                 <p>Your account is no longer protected by two-factor authentication. We strongly recommend re-enabling it in your security settings after logging in.</p>
                 <p>If you did not request this change, please contact your community administrator immediately.</p>',
                'Log In & Secure Your Account',
                $loginUrl,
                $tenant['name']
            );

            (\App\Core\Mailer::forCurrentTenant())->send(
                $user['email'],
                "Your two-factor authentication has been reset - {$tenantNameSafe}",
                $html
            );
        } catch (\Throwable $e) {
            Log::warning("[AdminUsers] Failed to send 2FA reset email to user #{$id}: " . $e->getMessage());
        }

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
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        $badgeSlug = trim($this->input('badge_slug', ''));
        if (empty($badgeSlug)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.badge_slug_required'), 'badge_slug', 422);
        }

        try {
            $this->gamificationService->awardBadgeByKey($id, $badgeSlug);
            ActivityLog::log($adminId, 'admin_award_badge', "Awarded badge '{$badgeSlug}' to user #{$id}");

            // Notify the user (bell notification only)
            try {
                $badgeDisplayName = ucwords(str_replace(['-', '_'], ' ', $badgeSlug));
                Notification::createNotification(
                    $id,
                    "You've been awarded the {$badgeDisplayName} badge!",
                    '/achievements',
                    'achievement',
                    false
                );
            } catch (\Throwable $e) {
                Log::warning("[AdminUsers] Failed to create badge award bell notification for user #{$id}: " . $e->getMessage());
            }

            return $this->respondWithData(['awarded' => true, 'user_id' => $id, 'badge_slug' => $badgeSlug], null, 201);
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to award badge to user #{$id}: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', __('api.create_failed', ['resource' => 'badge award']), null, 500);
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
            return $this->respondWithError('NOT_FOUND', __('api.badge_not_found'), null, 404);
        }

        DB::delete("DELETE FROM user_badges WHERE id = ? AND user_id = ? AND tenant_id = ?", [$badgeId, $id, $tenantId]);
        ActivityLog::log($adminId, 'admin_remove_badge', "Removed badge #{$badgeId} from user #{$id}");

        // Notify the user (bell notification only)
        try {
            $badgeDisplayName = $badge->badge_name ?? ucwords(str_replace(['-', '_'], ' ', $badge->badge_key ?? 'Unknown'));
            Notification::createNotification(
                $id,
                "The {$badgeDisplayName} badge has been removed from your profile.",
                '/achievements',
                'system',
                false
            );
        } catch (\Throwable $e) {
            Log::warning("[AdminUsers] Failed to create badge removal bell notification for user #{$id}: " . $e->getMessage());
        }

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
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        try {
            $this->gamificationService->runAllBadgeChecks($id);
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
            error_log("[AdminUsers] Badge recheck failed for user #{$id}: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', __('api.badge_recheck_failed'), null, 500);
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
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        try {
            $consents = $this->gdprService->getUserConsents($id);

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
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        $password = $this->input('password', '');
        if (strlen($password) < 8) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.password_min_length'), 'password', 422);
        }

        $hashed = password_hash($password, PASSWORD_ARGON2ID);
        DB::update("UPDATE users SET password_hash = ? WHERE id = ? AND tenant_id = ?", [$hashed, $id, $tenantId]);

        ActivityLog::log($adminId, 'admin_set_password', "Admin set password for user #{$id} ({$user['email']})");
        AuditLogService::logAdminAction('admin_set_password', $adminId, $id, ['email' => $user['email']]);

        // Notify the user (email only — they need to know their old password no longer works)
        // CRITICAL: Do NOT include the new password in the email
        try {
            $tenant = $this->resolveUserTenant($user);
            $firstName = htmlspecialchars($user['first_name'] ?? 'there', ENT_QUOTES, 'UTF-8');
            $tenantNameSafe = htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8');
            $loginUrl = TenantContext::getFrontendUrl() . $tenant['slug_prefix'] . '/login';

            $html = \App\Core\EmailTemplate::render(
                'Password Changed',
                "Security notice, {$firstName}",
                '<p>Your password on <strong>' . $tenantNameSafe . '</strong> has been reset by an administrator.</p>
                 <p>Your previous password will no longer work. Please use the new password provided to you by your administrator to log in.</p>
                 <p>If you did not expect this change, please contact your community administrator immediately.</p>',
                'Log In Now',
                $loginUrl,
                $tenant['name']
            );

            (\App\Core\Mailer::forCurrentTenant())->send(
                $user['email'],
                "Your password has been reset - {$tenantNameSafe}",
                $html
            );
        } catch (\Throwable $e) {
            Log::warning("[AdminUsers] Failed to send password reset email to user #{$id}: " . $e->getMessage());
        }

        return $this->respondWithData(['password_set' => true, 'id' => $id]);
    }

    // =========================================================================
    // Impersonate & Super Admin
    // =========================================================================

    /** POST /api/v2/admin/users/{id}/impersonate */
    public function impersonate($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        // Prevent impersonating super admins (security measure)
        if (!empty($user['is_super_admin']) || !empty($user['is_tenant_super_admin'])) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.cannot_impersonate_super_admin'), null, 403);
        }

        // Prevent self-impersonation
        if ($id === $adminId) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.cannot_impersonate_self'), null, 422);
        }

        // Check if target user is blocked by registration policy gates
        $userTenantId = (int) $user['tenant_id'];
        $gateBlock = $this->tenantSettingsService->checkLoginGatesForUser($user);

        try {
            // Generate a short-lived, single-use impersonation token (5 min TTL)
            $token = $this->tokenService->generateImpersonationToken($id, $userTenantId, $adminId);

            ActivityLog::log($adminId, 'admin_impersonate', "Impersonated user #{$id} ({$user['email']})");
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

            return $this->respondWithData($responseData);
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.create_failed', ['resource' => 'impersonation token']), null, 500);
        }
    }

    /** PUT /api/v2/admin/users/{id}/super-admin */
    public function setSuperAdmin($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        // Only super admins can grant/revoke tenant super admin status
        if (!$this->isCallerSuperAdmin($adminId)) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.only_super_admins_manage_status'), null, 403);
        }

        // Prevent self-modification
        if ($id === $adminId) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.cannot_modify_own_super_admin'), null, 422);
        }

        $grant = (bool) ($this->input('grant', false));

        try {
            // SECURITY: Scope user lookup by tenant_id to prevent cross-tenant IDOR
            $user = DB::selectOne(
                "SELECT id, email, first_name, last_name, tenant_id FROM users WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            if (!$user) {
                return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
            }

            // SECURITY: Scope UPDATE by tenant_id to prevent cross-tenant modification
            if ($grant) {
                DB::update(
                    "UPDATE users SET is_tenant_super_admin = 1, role = 'tenant_admin' WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                );
            } else {
                DB::update(
                    "UPDATE users SET is_tenant_super_admin = 0 WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                );
            }

            $action = $grant ? 'grant_tenant_super_admin' : 'revoke_tenant_super_admin';
            ActivityLog::log($adminId, $action, ($grant ? 'Granted' : 'Revoked') . " tenant super admin for user #{$id}: {$user->email} (tenant {$user->tenant_id})");
            AuditLogService::logAdminAction($action, $adminId, $id, ['email' => $user->email]);

            // Notify the user of role change (bell notification)
            try {
                $message = $grant
                    ? 'You have been granted Tenant Super Admin privileges.'
                    : 'Your Tenant Super Admin privileges have been removed.';
                Notification::createNotification(
                    $id,
                    $message,
                    null,
                    'system',
                    true
                );
            } catch (\Throwable $e) {
                Log::warning("[AdminUsers] Failed to create super admin role change notification for user #{$id}: " . $e->getMessage());
            }

            return $this->respondWithData(['id' => $id, 'is_tenant_super_admin' => $grant]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.update_failed', ['resource' => 'super admin status']), null, 500);
        }
    }

    /** PUT /api/v2/admin/users/{id}/global-super-admin */
    public function setGlobalSuperAdmin($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        // Only god users can set global super admin
        if (!User::isGod($adminId)) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.only_god_manage_global_super_admin'), null, 403);
        }

        // Prevent self-modification
        if ($id === $adminId) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.cannot_modify_own_super_admin'), null, 422);
        }

        $grant = (bool) ($this->input('grant', false));

        try {
            // SECURITY: Scope user lookup by tenant_id to prevent cross-tenant IDOR
            $user = DB::selectOne(
                "SELECT id, email, first_name, last_name, tenant_id FROM users WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            if (!$user) {
                return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
            }

            // SECURITY: Scope UPDATE by tenant_id to prevent cross-tenant modification
            DB::update(
                "UPDATE users SET is_super_admin = ? WHERE id = ? AND tenant_id = ?",
                [$grant ? 1 : 0, $id, $tenantId]
            );

            $action = $grant ? 'grant_global_super_admin' : 'revoke_global_super_admin';
            ActivityLog::log($adminId, $action, ($grant ? 'Granted' : 'Revoked') . " global super admin for user #{$id}: {$user->email} (tenant {$user->tenant_id})");
            AuditLogService::logAdminAction($action, $adminId, $id, ['email' => $user->email]);

            // Notify the user of role change (bell notification)
            try {
                $message = $grant
                    ? 'You have been granted Global Super Admin privileges.'
                    : 'Your Global Super Admin privileges have been removed.';
                Notification::createNotification(
                    $id,
                    $message,
                    null,
                    'system',
                    true
                );
            } catch (\Throwable $e) {
                Log::warning("[AdminUsers] Failed to create global super admin role change notification for user #{$id}: " . $e->getMessage());
            }

            return $this->respondWithData(['id' => $id, 'is_super_admin' => $grant]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.update_failed', ['resource' => 'global super admin status']), null, 500);
        }
    }

    // =========================================================================
    // Email-sending methods
    // =========================================================================

    /** POST /api/v2/admin/users/{id}/send-password-reset */
    public function sendPasswordReset($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        try {
            $token = bin2hex(random_bytes(32));
            $hashedToken = password_hash($token, PASSWORD_DEFAULT);

            // Store in password_resets table (same as user-initiated flow)
            DB::delete("DELETE FROM password_resets WHERE email = ?", [$user['email']]);
            DB::insert(
                "INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())",
                [$user['email'], $hashedToken]
            );

            $tenant = $this->resolveUserTenant($user);
            $tenantNameSafe = htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8');

            // Build frontend URL with defensive fallback
            $appUrl = TenantContext::getFrontendUrl();
            if (!$appUrl || str_contains($appUrl, 'api.')) {
                $appUrl = \App\Core\Env::get('APP_URL', 'https://app.project-nexus.ie');
                if (str_contains($appUrl, 'api.')) {
                    $appUrl = str_replace('api.', 'app.', $appUrl);
                }
            }
            $resetLink = $appUrl . $tenant['slug_prefix'] . "/password/reset?token={$token}";

            $html = \App\Core\EmailTemplate::render(
                "Password Reset",
                "Reset your password for {$tenantNameSafe}",
                "<p>Hello <strong>" . htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                <p>An administrator has requested a password reset for your account.</p>
                <p>Click the button below to set a new password. This link expires in 1 hour.</p>",
                "Reset Password",
                $resetLink,
                $tenant['name']
            );

            $mailer = \App\Core\Mailer::forCurrentTenant();
            $mailer->send($user['email'], "Password Reset - {$tenantNameSafe}", $html);

            ActivityLog::log($adminId, 'admin_send_password_reset', "Sent password reset email to user #{$id} ({$user['email']})");

            return $this->respondWithData(['sent' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to send password reset email for user #{$id}: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', __('api.create_failed', ['resource' => 'password reset email']), null, 500);
        }
    }

    /** POST /api/v2/admin/users/{id}/send-welcome-email */
    public function sendWelcomeEmail($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        $user = User::findById($id, true);
        if (!$user || $user['tenant_id'] != $tenantId) {
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        try {
            // Resolve tenant from the USER's tenant_id
            $resolvedTenant = $this->resolveUserTenant($user);
            $userTenantId = $resolvedTenant['tenant_id'];
            $tenantName = $resolvedTenant['name'];
            $tenantNameSafe = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');

            // Read tenant configuration for custom welcome email content
            $tenantRow = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$userTenantId]);
            $config = json_decode($tenantRow->configuration ?? '{}', true);
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
                $html = \App\Core\EmailTemplate::render(
                    "Welcome!",
                    "You are a member of {$tenantNameSafe}",
                    $mainMessage,
                    "Login & Get Started",
                    $loginLink,
                    $tenantName
                );
            }

            $mailer = \App\Core\Mailer::forCurrentTenant();
            $mailer->send($user['email'], $subject, $html);

            ActivityLog::log($adminId, 'admin_resend_welcome', "Resent welcome email to user #{$id} ({$user['email']})");

            return $this->respondWithData(['sent' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to send welcome email for user #{$id}: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', __('api.create_failed', ['resource' => 'welcome email']), null, 500);
        }
    }

    // =========================================================================
    // Import
    // =========================================================================

    /** POST /api/v2/admin/users/import */
    public function import(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.csv_no_file'), null, 400);
        }

        $file = $_FILES['csv_file'];
        $allowedTypes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($file['type'], $allowedTypes)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.csv_invalid_type'), null, 400);
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return $this->respondWithError('SERVER_ERROR', __('api.csv_could_not_read'), null, 500);
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return $this->respondWithError('VALIDATION_ERROR', __('api.csv_empty'), null, 400);
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
            return $this->respondWithError('VALIDATION_ERROR', __('api.csv_missing_columns', ['columns' => implode(', ', $missing)]), null, 400);
        }

        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];
        $row = 1;
        $defaultRole = request()->input('default_role', 'member');

        // SECURITY: Restrict allowed roles for CSV import to prevent privilege escalation (SEC-008)
        $allowedImportRoles = ['member', 'admin', 'broker'];
        if (!in_array($defaultRole, $allowedImportRoles, true)) {
            $defaultRole = 'member';
        }

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
                $existing = DB::selectOne(
                    "SELECT id FROM users WHERE email = ? AND tenant_id = ?",
                    [$email, $tenantId]
                );

                if ($existing) {
                    $results['errors'][] = "Row {$row}: User with email '{$email}' already exists";
                    $results['skipped']++;
                    continue;
                }

                // Create user with random temp password
                $password = bin2hex(random_bytes(16));
                $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

                DB::insert(
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
                        // SECURITY: Validate per-row role against allowlist (SEC-008)
                        in_array($record['role'] ?? $defaultRole, $allowedImportRoles, true) ? ($record['role'] ?? $defaultRole) : $defaultRole,
                    ]
                );

                // Seed federation settings for the new user
                $newUserId = (int) DB::getPdo()->lastInsertId();
                if ($newUserId > 0) {
                    try {
                        DB::statement(
                            "INSERT IGNORE INTO federation_user_settings (
                                user_id, federation_optin, profile_visible_federated,
                                messaging_enabled_federated, transactions_enabled_federated,
                                appear_in_federated_search, show_skills_federated,
                                show_location_federated, service_reach, opted_in_at, created_at
                            ) VALUES (?, 1, 1, 1, 1, 1, 1, 0, 'local_only', NOW(), NOW())",
                            [$newUserId]
                        );
                    } catch (\Exception $e) {
                        // Non-critical — federation settings can be seeded later
                    }
                }

                $results['imported']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$row}: " . $e->getMessage();
                $results['skipped']++;
            }
        }

        fclose($handle);

        ActivityLog::log($adminId, 'admin_bulk_import_users', "Bulk imported {$results['imported']} users ({$results['skipped']} skipped)");
        AuditLogService::logBulkImport($adminId, $results['imported'], $results['skipped'], $row - 1);

        return $this->respondWithData([
            'imported' => $results['imported'],
            'skipped' => $results['skipped'],
            'errors' => array_slice($results['errors'], 0, 50),
            'total_rows' => $row - 1,
        ]);
    }

    /** GET /api/v2/admin/users/import/template */
    public function importTemplate(): Response
    {
        $this->requireAdmin();

        // Build CSV content for template download
        $csvLines = [];
        $csvLines[] = ['first_name', 'last_name', 'email', 'phone', 'role'];
        $csvLines[] = ['Jane', 'Doe', 'jane@example.com', '+15551234567', 'member'];
        $csvLines[] = ['John', 'Smith', 'john@example.com', '', 'member'];

        // Build CSV string
        $output = fopen('php://temp', 'r+');
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        foreach ($csvLines as $line) {
            fputcsv($output, $line);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="user_import_template.csv"',
        ]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Check if the calling admin is a super admin (is_super_admin or is_tenant_super_admin).
     *
     * @param int $adminId The admin's user ID
     * @return bool
     */
    private function isCallerSuperAdmin(int $adminId): bool
    {
        $row = DB::selectOne(
            "SELECT is_super_admin, is_tenant_super_admin FROM users WHERE id = ?",
            [$adminId]
        );

        if (!$row) {
            return false;
        }

        return !empty($row->is_super_admin) || !empty($row->is_tenant_super_admin);
    }

    /**
     * Resolve the tenant name and slug for a user's tenant.
     * Uses the user's tenant_id to look up the correct values.
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

        $tenant = DB::selectOne("SELECT name, slug FROM tenants WHERE id = ?", [$userTenantId]);
        if ($tenant) {
            $tenantName = $tenant->name;
            $slug = $tenant->slug ?? '';
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
            $creditAmount = (int) $this->tenantSettingsService->get($userTenantId, 'welcome_credits', 5);
            if ($creditAmount <= 0) {
                return 0;
            }

            DB::beginTransaction();

            try {
                // Lock the user row to prevent concurrent double-credit (TOCTOU race)
                DB::select(
                    "SELECT id FROM users WHERE id = ? AND tenant_id = ? FOR UPDATE",
                    [$userId, $userTenantId]
                );

                // Check if a welcome bonus was already granted (true idempotency key)
                $existing = DB::selectOne(
                    "SELECT id FROM transactions WHERE tenant_id = ? AND receiver_id = ? AND description LIKE '[Welcome Bonus]%' LIMIT 1",
                    [$userTenantId, $userId]
                );

                if ($existing) {
                    DB::rollBack();
                    error_log("[AdminUsers] Welcome credits already exist for user #{$userId} (tenant {$userTenantId}) — skipping");
                    return 0;
                }

                // Update user balance (scoped by user's tenant)
                DB::update(
                    "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                    [$creditAmount, $userId, $userTenantId]
                );

                // Create transaction record for audit trail
                DB::insert(
                    "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, status, created_at)
                     VALUES (?, ?, ?, ?, ?, 'completed', NOW())",
                    [$userTenantId, $userId, $userId, $creditAmount, "[Welcome Bonus] New member welcome credits (approved by admin #{$adminId})"]
                );

                DB::commit();

                ActivityLog::log($adminId, 'welcome_credits_issued', "Awarded {$creditAmount} welcome credits to user #{$userId} ({$user['email']}) on approval");
                error_log("[AdminUsers] Granted {$creditAmount} welcome credits to user #{$userId} (tenant {$userTenantId})");

                return $creditAmount;
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to grant welcome credits to user #{$user['id']}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send the combined welcome email when a user is approved.
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

            $html = \App\Core\EmailTemplate::render(
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

            $result = (\App\Core\Mailer::forCurrentTenant())->send($user['email'], $subject, $html);

            if ($result) {
                error_log("[AdminUsers] Welcome email sent to user #{$user['id']} (credits: {$creditsAwarded})");
            } else {
                error_log("[AdminUsers] Mailer returned false for welcome email to user #{$user['id']} — check SMTP/Gmail config");
            }

            return (bool) $result;
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to send welcome email to user #{$user['id']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create an in-app notification for the approved user.
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

            Notification::createNotification(
                (int) $user['id'],
                $message,
                '/dashboard',
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

            $html = \App\Core\EmailTemplate::render(
                'Account Reactivated',
                "Welcome back, {$firstName}!",
                '<p>Your account on ' . $tenantNameSafe . ' has been reactivated by an administrator.</p>
                 <p>You can now log in and access the platform again.</p>',
                'Log In Now',
                $loginUrl,
                $tenant['name']
            );

            $result = (\App\Core\Mailer::forCurrentTenant())->send(
                $user['email'],
                "Your account has been reactivated - {$tenantNameSafe}",
                $html
            );

            if ($result) {
                error_log("[AdminUsers] Reactivation email sent to user #{$user['id']}");
            } else {
                error_log("[AdminUsers] Mailer returned false for reactivation email to user #{$user['id']} — check SMTP/Gmail config");
            }

            return (bool) $result;
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to send reactivation email to user #{$user['id']}: " . $e->getMessage());
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

            Notification::createNotification(
                (int) $user['id'],
                "Your account has been reactivated. Welcome back to {$tenant['name']}!",
                '/dashboard',
                'system',
                true,
                $tenant['tenant_id']
            );
        } catch (\Throwable $e) {
            error_log("[AdminUsers] Failed to create reactivation notification for user #{$user['id']}: " . $e->getMessage());
        }
    }
}
