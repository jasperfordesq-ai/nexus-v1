<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Models\User;
use Nexus\Services\UserInsightsService;

/**
 * InsightsController
 *
 * Handles user-facing transaction insights and analytics.
 */
class InsightsController
{
    /**
     * User insights dashboard
     */
    public function index()
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        // Get user info
        $user = User::findById($userId);

        // Get comprehensive insights
        $insights = UserInsightsService::getInsights($userId);

        // Get monthly trends for chart
        $trends = UserInsightsService::getMonthlyTrends($userId, 6);

        // Get partner stats
        $partnerStats = UserInsightsService::getPartnerStats($userId);

        // Get top partners
        $topGivingPartners = UserInsightsService::getTopPartners($userId, 'giving', 5);
        $topReceivingPartners = UserInsightsService::getTopPartners($userId, 'receiving', 5);

        // Get category breakdown if available
        $categories = UserInsightsService::getCategoryBreakdown($userId, 6);

        // Get activity streak
        $streak = UserInsightsService::getStreak($userId);

        View::render('wallet/insights', [
            'pageTitle' => 'My Insights',
            'user' => $user,
            'insights' => $insights,
            'trends' => $trends,
            'partnerStats' => $partnerStats,
            'topGivingPartners' => $topGivingPartners,
            'topReceivingPartners' => $topReceivingPartners,
            'categories' => $categories,
            'streak' => $streak,
        ]);
    }

    /**
     * API: Get insights data as JSON
     */
    public function apiInsights()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $months = (int) ($_GET['months'] ?? 6);

        $data = [
            'success' => true,
            'insights' => UserInsightsService::getInsights($userId, $months),
            'trends' => UserInsightsService::getMonthlyTrends($userId, $months),
            'partnerStats' => UserInsightsService::getPartnerStats($userId, $months),
        ];

        echo json_encode($data);
    }

    /**
     * Helper: Require authentication
     */
    private function requireAuth()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }
}
