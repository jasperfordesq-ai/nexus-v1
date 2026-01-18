<?php

namespace Nexus\Controllers\Api;

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
}
