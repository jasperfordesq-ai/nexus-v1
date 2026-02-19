<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Middleware;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\TokenService;

/**
 * MaintenanceModeMiddleware
 *
 * Blocks all non-admin requests when maintenance mode is enabled.
 * Admins, tenant admins, and super admins can still access the platform.
 */
class MaintenanceModeMiddleware
{
    /**
     * Routes that are always accessible even in maintenance mode
     */
    private const EXEMPT_ROUTES = [
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
        '/favicon.ico',
    ];

    /**
     * Check if maintenance mode is enabled and block non-admin users.
     *
     * @throws \Exception if maintenance mode is active and user is not admin
     */
    public static function check(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Skip check for exempt routes
        foreach (self::EXEMPT_ROUTES as $route) {
            if (strpos($requestUri, $route) === 0) {
                return;
            }
        }

        // Get maintenance mode setting
        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            return; // No tenant context, skip check
        }

        try {
            $setting = Database::query(
                "SELECT setting_value FROM tenant_settings
                 WHERE tenant_id = ? AND setting_key = 'general.maintenance_mode'",
                [$tenantId]
            )->fetch();

            $maintenanceMode = $setting && ($setting['setting_value'] === 'true' || $setting['setting_value'] === '1');

            if (!$maintenanceMode) {
                return; // Not in maintenance mode
            }

            // Check if user is admin
            if (self::isUserAdmin()) {
                return; // Admin can access
            }

            // Block with maintenance message
            self::showMaintenancePage();

        } catch (\Exception $e) {
            // If tenant_settings table doesn't exist, assume not in maintenance mode
            return;
        }
    }

    /**
     * Check if the current user is an admin
     */
    private static function isUserAdmin(): bool
    {
        // Check Bearer token auth first (for API requests)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            try {
                $token = $matches[1];
                $payload = TokenService::verifyAccessToken($token);

                if ($payload && isset($payload['user_id'])) {
                    $userId = $payload['user_id'];
                    $tenantId = $payload['tenant_id'] ?? null;

                    $user = Database::query(
                        "SELECT role, is_super_admin, is_tenant_super_admin
                         FROM users WHERE id = ?" . ($tenantId ? " AND tenant_id = ?" : ""),
                        $tenantId ? [$userId, $tenantId] : [$userId]
                    )->fetch();

                    if ($user) {
                        return in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'super_admin'])
                            || !empty($user['is_super_admin'])
                            || !empty($user['is_tenant_super_admin']);
                    }
                }
            } catch (\Exception $e) {
                // Token validation failed, continue to session check
            }
        }

        // Check session auth
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            $tenantId = TenantContext::getId();

            $user = Database::query(
                "SELECT role, is_super_admin, is_tenant_super_admin
                 FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch();

            if ($user) {
                return in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'super_admin'])
                    || !empty($user['is_super_admin'])
                    || !empty($user['is_tenant_super_admin']);
            }
        }

        return false;
    }

    /**
     * Show maintenance mode page and exit
     */
    private static function showMaintenancePage(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // For API requests, return JSON error
        if (strpos($requestUri, '/api/') === 0) {
            header('Content-Type: application/json');
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'error' => 'Platform is currently under maintenance. Please check back soon.',
                'code' => 'MAINTENANCE_MODE',
            ]);
            exit;
        }

        // For HTML requests, let React handle the maintenance page
        // React's TenantShell will check maintenance mode and show MaintenancePage.tsx
        // This provides a polished HeroUI component instead of plain HTML
        return;
    }
}
