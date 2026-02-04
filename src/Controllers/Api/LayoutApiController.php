<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Csrf;
use Nexus\Services\LayoutHelper;

/**
 * LayoutApiController - Handle layout switching via AJAX
 *
 * Provides session-based layout switching without URL pollution.
 * Used by layout-switch-helper.js v2.0 for clean SPA-style layout changes.
 */
class LayoutApiController
{
    /**
     * POST /api/layout-switch
     *
     * Switch the active layout via AJAX request.
     * Expects JSON body: { "layout": "modern" | "civicone" }
     */
    public function switch()
    {
        // Ensure proper headers for AJAX
        header('Content-Type: application/json');

        // Only accept POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed. Use POST.'
            ]);
            exit;
        }

        // Security: Verify CSRF token for session-based requests
        Csrf::verifyOrDieJson();

        // Verify AJAX request (optional but recommended)
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['layout'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Missing layout parameter'
            ]);
            exit;
        }

        $targetLayout = $input['layout'];

        // Use LayoutHelper to generate response
        $response = LayoutHelper::generateSwitchResponse($targetLayout);

        // Add current timestamp for debugging
        $response['timestamp'] = time();

        http_response_code($response['success'] ? 200 : 400);
        echo json_encode($response);
        exit;
    }

    /**
     * GET /api/layout-switch
     *
     * Get the current active layout.
     */
    public function current()
    {
        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'layout' => LayoutHelper::get(),
            'available' => LayoutHelper::getValidLayouts(),
            'default' => LayoutHelper::getDefault()
        ]);
        exit;
    }

    /**
     * GET /api/layout-debug
     *
     * Debug endpoint to see layout state
     * SECURITY: Restricted to admin users only in development environment
     *
     * Note: This endpoint is session-based only (layout preference is a browser/UI concern).
     * Bearer token clients should not need this endpoint.
     */
    public function debug()
    {
        header('Content-Type: application/json');

        // SECURITY: Only allow in development and for admins
        $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');

        // Check admin status from session (this is a session-only debug endpoint)
        $userRole = $_SESSION['user_role'] ?? null;
        $isAdmin = $userRole && in_array($userRole, ['admin', 'super_admin']);

        if ($appEnv === 'production' || !$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Debug endpoint disabled in production or requires admin access']);
            exit;
        }

        // Get tenant info
        $tenantId = \Nexus\Core\TenantContext::getId() ?? 1;
        $sessionKey = 'nexus_active_layout_' . $tenantId;

        echo json_encode([
            'tenant_id' => $tenantId,
            'session_key' => $sessionKey,
            'session_value' => $_SESSION[$sessionKey] ?? 'NOT SET',
            'layout_helper_get' => LayoutHelper::get(),
            'note' => 'This debug endpoint is session-based only (layout is a browser UI concern)'
        ], JSON_PRETTY_PRINT);
        exit;
    }
}
