<?php

namespace Nexus\Middleware;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;

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
        '/api/auth/login',
        '/api/auth/logout',
        '/api/v2/tenant/bootstrap',
        '/api/v2/tenants',
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
        try {
            // Try API auth first
            $user = ApiAuth::authenticate();
            if ($user) {
                return in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'super_admin'])
                    || !empty($user['is_super_admin'])
                    || !empty($user['is_tenant_super_admin']);
            }
        } catch (\Exception $e) {
            // API auth failed, continue
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

        // If it's an API request, return JSON
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

        // Otherwise show HTML page
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: 3600'); // Suggest retry in 1 hour

        $tenantName = TenantContext::getSetting('name') ?? 'Project NEXUS';

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - {$tenantName}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: #1a202c;
        }
        .container {
            background: white;
            border-radius: 1rem;
            padding: 3rem 2rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #1a202c;
        }
        p {
            font-size: 1.1rem;
            color: #4a5568;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        .footer {
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #718096;
        }
        @media (max-width: 640px) {
            .container { padding: 2rem 1.5rem; }
            h1 { font-size: 1.5rem; }
            p { font-size: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>We'll be back soon!</h1>
        <p>
            <strong>{$tenantName}</strong> is currently undergoing scheduled maintenance to improve your experience.
        </p>
        <p>
            We apologize for any inconvenience. Please check back in a little while.
        </p>
        <div class="footer">
            Thank you for your patience!
        </div>
    </div>
</body>
</html>
HTML;
        exit;
    }
}
