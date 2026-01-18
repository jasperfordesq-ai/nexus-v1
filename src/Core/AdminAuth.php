<?php

namespace Nexus\Core;

/**
 * AdminAuth - Centralized admin authentication and authorization
 *
 * Hierarchy (highest to lowest):
 * 1. God (is_god=1) - Bypasses ALL permission checks, can manage everything
 * 2. Super Admin (is_super_admin=1) - Can access all tenants
 * 3. Admin (role=admin) - Full access to their tenant
 * 4. Tenant Admin (role=tenant_admin) - Can manage their tenant
 * 5. Newsletter Admin (role=newsletter_admin) - Newsletter module only
 * 6. Member (role=member) - Standard user
 */
class AdminAuth
{
    /**
     * Check if current user is god (highest privilege level)
     */
    public static function isGod(): bool
    {
        return !empty($_SESSION['is_god']);
    }

    /**
     * Check if current user is a super admin
     */
    public static function isSuperAdmin(): bool
    {
        return !empty($_SESSION['is_super_admin']) || self::isGod();
    }

    /**
     * Check if current user has any admin privileges
     */
    public static function isAdmin(): bool
    {
        if (self::isGod()) return true;
        if (self::isSuperAdmin()) return true;

        $role = $_SESSION['user_role'] ?? '';
        return in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_admin']);
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Require admin access - redirects to login or shows 403 if not authorized
     * God users bypass all checks
     *
     * @param bool $jsonResponse Return JSON error instead of HTML
     * @param bool $checkTenant Verify user belongs to current tenant (skipped for super/god)
     */
    public static function requireAdmin(bool $jsonResponse = false, bool $checkTenant = true): void
    {
        if (!self::isLoggedIn()) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // GOD MODE: Bypass all permission checks
        if (self::isGod()) {
            return;
        }

        if (!self::isAdmin()) {
            self::forbidden($jsonResponse);
        }

        // Verify tenant match (unless super admin or god)
        if ($checkTenant && !self::isSuperAdmin()) {
            $currentUser = Database::query("SELECT tenant_id FROM users WHERE id = ?", [$_SESSION['user_id']])->fetch();
            if ((int)($currentUser['tenant_id'] ?? 0) !== (int)TenantContext::getId()) {
                self::forbidden($jsonResponse);
            }
        }
    }

    /**
     * Require super admin access
     * God users bypass all checks
     */
    public static function requireSuperAdmin(bool $jsonResponse = false): void
    {
        if (!self::isLoggedIn()) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // GOD MODE: Bypass all permission checks
        if (self::isGod()) {
            return;
        }

        if (!self::isSuperAdmin()) {
            self::forbidden($jsonResponse);
        }
    }

    /**
     * Require god access (highest level)
     */
    public static function requireGod(bool $jsonResponse = false): void
    {
        if (!self::isLoggedIn()) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        if (!self::isGod()) {
            self::forbidden($jsonResponse);
        }
    }

    /**
     * Check if current user can manage the target user
     * God can manage anyone, super admins can manage non-gods, etc.
     */
    public static function canManageUser(array $targetUser): bool
    {
        // God can manage anyone
        if (self::isGod()) {
            return true;
        }

        // Can't manage god users unless you're god
        if (!empty($targetUser['is_god'])) {
            return false;
        }

        // Super admins can manage non-god users
        if (self::isSuperAdmin()) {
            return true;
        }

        // Regular admins can only manage users in their tenant who aren't super admins
        if (self::isAdmin()) {
            $targetTenant = $targetUser['tenant_id'] ?? 0;
            $currentTenant = TenantContext::getId();

            // Must be same tenant
            if ((int)$targetTenant !== (int)$currentTenant) {
                return false;
            }

            // Can't manage super admins
            if (!empty($targetUser['is_super_admin'])) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Check if current user can grant/revoke super admin status
     */
    public static function canManageSuperAdmins(): bool
    {
        return self::isGod();
    }

    /**
     * Check if current user can impersonate the target user
     */
    public static function canImpersonate(array $targetUser): bool
    {
        // God can impersonate anyone
        if (self::isGod()) {
            return true;
        }

        // Can't impersonate yourself
        if (($targetUser['id'] ?? 0) == ($_SESSION['user_id'] ?? 0)) {
            return false;
        }

        // Can't impersonate god users
        if (!empty($targetUser['is_god'])) {
            return false;
        }

        // Super admins can impersonate non-god, non-super-admin users
        if (self::isSuperAdmin()) {
            return empty($targetUser['is_super_admin']);
        }

        // Regular admins can only impersonate users in their tenant
        if (self::isAdmin()) {
            $targetTenant = $targetUser['tenant_id'] ?? 0;
            $currentTenant = TenantContext::getId();

            if ((int)$targetTenant !== (int)$currentTenant) {
                return false;
            }

            // Can't impersonate admins or super admins
            $targetRole = $targetUser['role'] ?? 'member';
            if (in_array($targetRole, ['admin', 'super_admin']) || !empty($targetUser['is_super_admin'])) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Show 403 forbidden error
     */
    private static function forbidden(bool $jsonResponse = false): void
    {
        if ($jsonResponse) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }

        header('HTTP/1.0 403 Forbidden');
        $basePath = TenantContext::getBasePath();
        echo "<h1>403 Forbidden</h1><p>You do not have permission to access this area.</p><a href='{$basePath}/dashboard'>Go Home</a>";
        exit;
    }

    /**
     * Get current user's privilege level as a string
     */
    public static function getPrivilegeLevel(): string
    {
        if (self::isGod()) return 'god';
        if (self::isSuperAdmin()) return 'super_admin';
        if (self::isAdmin()) return 'admin';
        if (self::isLoggedIn()) return 'member';
        return 'guest';
    }
}
