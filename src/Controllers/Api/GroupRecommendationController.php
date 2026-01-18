<?php

namespace Nexus\Controllers\Api;

use Nexus\Services\GroupRecommendationEngine;
use Nexus\Core\TenantContext;

/**
 * GroupRecommendationController
 *
 * API endpoints for group discovery and recommendations
 */
class GroupRecommendationController
{
    /**
     * Get personalized group recommendations for current user
     *
     * GET /api/recommendations/groups?limit=10&type_id=5
     */
    public function index()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));

        $options = [];
        if (isset($_GET['type_id'])) {
            $options['type_id'] = (int)$_GET['type_id'];
        }

        $recommendations = GroupRecommendationEngine::getRecommendations($userId, $limit, $options);

        echo json_encode([
            'success' => true,
            'recommendations' => $recommendations,
            'count' => count($recommendations),
        ]);
    }

    /**
     * Track user interaction with recommendation
     *
     * POST /api/recommendations/track
     * Body: {group_id: 123, action: 'clicked'}
     */
    public function track()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['group_id']) || empty($input['action'])) {
            http_response_code(400);
            echo json_encode(['error' => 'group_id and action required']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $groupId = (int)$input['group_id'];
        $action = $input['action'];

        // Validate action
        $validActions = ['viewed', 'clicked', 'joined', 'dismissed'];
        if (!in_array($action, $validActions)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            return;
        }

        GroupRecommendationEngine::trackInteraction($userId, $groupId, $action);

        echo json_encode(['success' => true]);
    }

    /**
     * Get recommendation performance metrics (admin only)
     *
     * GET /api/recommendations/metrics?days=30
     */
    public function metrics()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        // Check if user is admin
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin']);

        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            return;
        }

        $days = min(365, max(1, (int)($_GET['days'] ?? 30)));

        $metrics = GroupRecommendationEngine::getPerformanceMetrics($days);

        echo json_encode([
            'success' => true,
            'metrics' => $metrics,
            'period_days' => $days,
        ]);
    }

    /**
     * Get "similar to this group" recommendations
     *
     * GET /api/recommendations/similar/{groupId}?limit=5
     */
    public function similar($groupId)
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $limit = min(20, max(1, (int)($_GET['limit'] ?? 5)));

        // Get recommendations but exclude the current group
        $options = ['exclude_ids' => [(int)$groupId]];

        $recommendations = GroupRecommendationEngine::getRecommendations($userId, $limit, $options);

        echo json_encode([
            'success' => true,
            'similar_groups' => $recommendations,
            'count' => count($recommendations),
        ]);
    }
}
