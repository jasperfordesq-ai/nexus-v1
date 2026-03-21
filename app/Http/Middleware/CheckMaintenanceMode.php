<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Core\Database;
use App\Core\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel middleware wrapper for tenant-level maintenance mode.
 *
 * Checks the tenant_settings table for general.maintenance_mode = 'true'.
 * Admins bypass; exempt routes (auth, bootstrap, health) are always allowed.
 */
class CheckMaintenanceMode
{
    private const EXEMPT_PREFIXES = [
        '/api/v2/admin/',
        '/admin/',
        '/admin-legacy/',
        '/super-admin/',
        '/api/auth/',
        '/api/v2/tenant/bootstrap',
        '/api/v2/tenants',
        '/api/v2/messages/unread-count',
        '/api/v2/notifications/counts',
        '/health.php',
        '/up',
        '/favicon.ico',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        // Skip exempt routes
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            return $next($request);
        }

        try {
            $setting = Database::query(
                "SELECT setting_value FROM tenant_settings
                 WHERE tenant_id = ? AND setting_key = 'general.maintenance_mode'",
                [$tenantId]
            )->fetch();

            $isMaintenanceMode = $setting
                && ($setting['setting_value'] === 'true' || $setting['setting_value'] === '1');

            if (!$isMaintenanceMode) {
                return $next($request);
            }

            // Let admins through
            if ($this->isAdmin($request, $tenantId)) {
                return $next($request);
            }

            // Return 503 for API requests
            return response()->json([
                'success' => false,
                'error' => 'Platform is currently under maintenance. Please check back soon.',
                'code' => 'MAINTENANCE_MODE',
            ], 503, ['Retry-After' => '300']);

        } catch (\Throwable $e) {
            // If anything fails, don't block traffic
            return $next($request);
        }
    }

    private function isAdmin(Request $request, int $tenantId): bool
    {
        // Check Sanctum/token auth (Laravel auth already resolved by this point in some routes)
        $user = $request->user();
        if ($user) {
            return in_array($user->role ?? '', ['admin', 'tenant_admin', 'super_admin'])
                || !empty($user->is_super_admin)
                || !empty($user->is_tenant_super_admin);
        }

        // Fallback: check Bearer token manually (for routes without auth middleware)
        $token = $request->bearerToken();
        if ($token) {
            try {
                $payload = \App\Services\TokenService::validateToken($token);
                if ($payload && isset($payload['user_id'])) {
                    $row = Database::query(
                        "SELECT role, is_super_admin, is_tenant_super_admin
                         FROM users WHERE id = ? AND tenant_id = ?",
                        [$payload['user_id'], $tenantId]
                    )->fetch();

                    if ($row) {
                        return in_array($row['role'] ?? '', ['admin', 'tenant_admin', 'super_admin'])
                            || !empty($row['is_super_admin'])
                            || !empty($row['is_tenant_super_admin']);
                    }
                }
            } catch (\Throwable $e) {
                // Token invalid, not admin
            }
        }

        return false;
    }
}
