<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Illuminate\Support\Facades\DB;

/**
 * SuperPanelAccess Middleware - Gatekeeper for the Super Admin Panel.
 * Direct implementation replacing Nexus\Middleware\SuperPanelAccess delegation.
 *
 * ACCESS RULES:
 * - Master Tenant (id=1) + is_tenant_super_admin: GLOBAL access (sees all tenants)
 * - Regional Tenant (allows_subtenants=1) + is_tenant_super_admin: SUBTREE access
 * - Standard Tenant (allows_subtenants=0): NO Super Panel access
 */
class SuperPanelAccess
{
    private static ?array $currentAccess = null;

    /**
     * Main gatekeeper - call at start of any Super Admin route.
     */
    public static function handle(): void
    {
        if (!self::check()) {
            self::denyAccess();
        }
    }

    /**
     * Check if current user can access Super Admin Panel.
     */
    public static function check(): bool
    {
        $access = self::getAccess();
        return $access['granted'];
    }

    /**
     * Get full access context for current user.
     * Cached per request for performance.
     *
     * @param int|null $userId Optional user ID for stateless (JWT) API auth.
     */
    public static function getAccess(?int $userId = null): array
    {
        if (self::$currentAccess !== null) {
            return self::$currentAccess;
        }

        // Default: no access
        self::$currentAccess = [
            'granted' => false,
            'level' => 'none',
            'user_id' => null,
            'tenant_id' => null,
            'tenant_name' => null,
            'tenant_path' => null,
            'tenant_depth' => null,
            'scope' => 'none',
            'can_create_tenants' => false,
            'max_depth' => 0,
            'reason' => 'Not authenticated'
        ];

        // Resolve user ID: prefer explicit param, fall back to session
        $effectiveUserId = $userId ?? ($_SESSION['user_id'] ?? null);
        if (empty($effectiveUserId)) {
            return self::$currentAccess;
        }

        // Get user + their tenant info
        $results = DB::select("
            SELECT
                u.id as user_id,
                u.tenant_id,
                u.role,
                u.is_super_admin,
                u.is_tenant_super_admin,
                t.name as tenant_name,
                t.path as tenant_path,
                t.depth as tenant_depth,
                t.allows_subtenants,
                t.max_depth
            FROM users u
            JOIN tenants t ON u.tenant_id = t.id
            WHERE u.id = ?
        ", [$effectiveUserId]);

        $user = $results[0] ?? null;

        if (!$user) {
            self::$currentAccess['reason'] = 'User not found';
            return self::$currentAccess;
        }

        // RULE 1: Must have tenant_super_admin flag OR is_super_admin (legacy)
        if (!$user->is_tenant_super_admin && !$user->is_super_admin) {
            self::$currentAccess['reason'] = 'Not a Super Admin for any tenant';
            return self::$currentAccess;
        }

        // RULE 2: Their tenant must allow sub-tenants OR be Master
        $isMaster = ((int)$user->tenant_id === 1);

        if (!$isMaster && !$user->allows_subtenants) {
            self::$currentAccess['reason'] = 'Tenant does not have sub-tenant capability';
            return self::$currentAccess;
        }

        // ACCESS GRANTED - determine scope
        self::$currentAccess = [
            'granted' => true,
            'level' => $isMaster ? 'master' : 'regional',
            'user_id' => (int)$user->user_id,
            'tenant_id' => (int)$user->tenant_id,
            'tenant_name' => $user->tenant_name,
            'tenant_path' => $user->tenant_path,
            'tenant_depth' => (int)$user->tenant_depth,
            'scope' => $isMaster ? 'global' : 'subtree',
            'can_create_tenants' => (bool)$user->allows_subtenants,
            'max_depth' => (int)$user->max_depth,
            'reason' => 'Access granted'
        ];

        return self::$currentAccess;
    }

    /**
     * Check if current user can view/manage a specific tenant.
     */
    public static function canAccessTenant(int $targetTenantId): bool
    {
        $access = self::getAccess();

        if (!$access['granted']) {
            return false;
        }

        // Master sees all
        if ($access['level'] === 'master') {
            return true;
        }

        // Own tenant is always accessible
        if ($targetTenantId === $access['tenant_id']) {
            return true;
        }

        // Regional: check if target is in their subtree
        $results = DB::select(
            "SELECT path FROM tenants WHERE id = ?",
            [$targetTenantId]
        );

        $target = $results[0] ?? null;

        if (!$target) {
            return false;
        }

        return str_starts_with($target->path, $access['tenant_path']);
    }

    /**
     * Check if current user can MANAGE (edit/delete) a specific tenant.
     */
    public static function canManageTenant(int $targetTenantId): bool
    {
        $access = self::getAccess();

        if (!$access['granted']) {
            return false;
        }

        // Check god status via DB
        $isGod = !empty($access['user_id'])
            && class_exists('Nexus\\Models\\User')
            && \Nexus\Models\User::isGod($access['user_id']);

        // Cannot manage own tenant (only view) - unless god
        if ($targetTenantId === $access['tenant_id']) {
            return $isGod;
        }

        // Master can manage everything except Master itself (unless god)
        if ($access['level'] === 'master') {
            if ($targetTenantId === 1) {
                return $isGod;
            }
            return true;
        }

        // Regional: can only manage descendants
        return self::canAccessTenant($targetTenantId);
    }

    /**
     * Check if current user can CREATE a sub-tenant under given parent.
     */
    public static function canCreateSubtenantUnder(int $parentTenantId): array
    {
        $access = self::getAccess();

        if (!$access['granted']) {
            return ['allowed' => false, 'reason' => 'No Super Admin access'];
        }

        if (!self::canAccessTenant($parentTenantId)) {
            return ['allowed' => false, 'reason' => 'Parent tenant not in your scope'];
        }

        $results = DB::select("
            SELECT id, name, path, depth, allows_subtenants, max_depth
            FROM tenants WHERE id = ?
        ", [$parentTenantId]);

        $parent = $results[0] ?? null;

        if (!$parent) {
            return ['allowed' => false, 'reason' => 'Parent tenant not found'];
        }

        if (!$parent->allows_subtenants) {
            return ['allowed' => false, 'reason' => "'{$parent->name}' cannot have sub-tenants"];
        }

        $newDepth = (int)$parent->depth + 1;
        $maxDepth = (int)$parent->max_depth;
        if ($maxDepth > 0 && $newDepth > $maxDepth) {
            return ['allowed' => false, 'reason' => 'Maximum hierarchy depth reached'];
        }

        return [
            'allowed' => true,
            'reason' => 'OK',
            'new_depth' => $newDepth,
            'parent_path' => $parent->path
        ];
    }

    /**
     * Get the scope SQL clause for tenant filtering.
     *
     * @param string $tableAlias Table alias (e.g., 't' for 't.id')
     * @return array ['sql' => string, 'params' => array]
     */
    public static function getScopeClause(string $tableAlias = 't'): array
    {
        $access = self::getAccess();

        if (!$access['granted']) {
            return [
                'sql' => "1 = 0",
                'params' => []
            ];
        }

        if ($access['level'] === 'master') {
            return [
                'sql' => "1 = 1",
                'params' => []
            ];
        }

        return [
            'sql' => "{$tableAlias}.path LIKE ?",
            'params' => [$access['tenant_path'] . '%']
        ];
    }

    /**
     * Reset cached access (useful for testing).
     */
    public static function reset(): void
    {
        self::$currentAccess = null;
    }

    /**
     * Deny access and exit.
     */
    private static function denyAccess(): void
    {
        $access = self::getAccess();

        http_response_code(403);

        error_log(sprintf(
            "SuperPanel ACCESS DENIED: user=%s, tenant=%s, reason=%s, ip=%s",
            $_SESSION['user_id'] ?? 'none',
            $_SESSION['tenant_id'] ?? 'none',
            $access['reason'],
            ClientIp::get()
        ));

        if (self::isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Access Denied',
                'message' => 'You do not have Super Admin Panel access',
                'reason' => $access['reason'],
                'code' => 'SUPER_PANEL_ACCESS_DENIED'
            ]);
        } else {
            if (file_exists(__DIR__ . '/../../views/errors/403-super-panel.php')) {
                include __DIR__ . '/../../views/errors/403-super-panel.php';
            } else {
                echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body>';
                echo '<h1>403 - Access Denied</h1>';
                echo '<p>You do not have permission to access the Super Admin Panel.</p>';
                echo '<p>Reason: ' . htmlspecialchars($access['reason']) . '</p>';
                echo '<p><a href="' . TenantContext::getBasePath() . '/admin">Return to Platform Admin</a></p>';
                echo '</body></html>';
            }
        }

        exit;
    }

    /**
     * Check if current request is an API request.
     */
    private static function isApiRequest(): bool
    {
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return str_contains($acceptHeader, 'application/json')
            || str_contains($contentType, 'application/json')
            || str_starts_with($uri, '/api/')
            || str_starts_with($uri, '/super-admin/api/');
    }
}
