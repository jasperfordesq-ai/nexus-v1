<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Models\Category;
use Nexus\Services\SmartMatchingEngine;
use Nexus\Services\SmartMatchingAnalyticsService;
use Nexus\Services\GeocodingService;
use Nexus\Services\MatchingService;

/**
 * SmartMatchingController - Admin Dashboard for Smart Matching Engine
 *
 * Provides admin interface for:
 * - Viewing matching analytics
 * - Configuring algorithm weights
 * - Managing match cache
 * - Monitoring conversions
 */
class SmartMatchingController
{
    private function requireAdmin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            header('HTTP/1.0 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>Access Denied.</p>";
            exit;
        }
    }

    /**
     * Main dashboard - overview of matching performance
     */
    public function index(): void
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();

        // Get comprehensive dashboard data
        $dashboardData = SmartMatchingAnalyticsService::getDashboardSummary();
        $config = SmartMatchingEngine::getConfig();
        $userEngagement = SmartMatchingAnalyticsService::getUserEngagement();

        View::render('admin/smart-matching/index', [
            'stats' => $dashboardData['stats'],
            'score_distribution' => $dashboardData['score_distribution'],
            'conversion_funnel' => $dashboardData['conversion_funnel'],
            'top_categories' => $dashboardData['top_categories'],
            'recent_activity' => $dashboardData['recent_activity'],
            'geocoding_status' => $dashboardData['geocoding_status'],
            'user_engagement' => $userEngagement,
            'config' => $config,
            'page_title' => 'Smart Matching Dashboard',
        ]);
    }

    /**
     * Detailed analytics page
     */
    public function analytics(): void
    {
        $this->requireAdmin();

        $weeklyTrends = SmartMatchingAnalyticsService::getWeeklyTrends(12);
        $dailyTrends = SmartMatchingAnalyticsService::getDailyTrends(30);
        $scoreDistribution = SmartMatchingAnalyticsService::getScoreDistribution();
        $distanceDistribution = SmartMatchingAnalyticsService::getDistanceDistribution();
        $conversionMetrics = SmartMatchingAnalyticsService::getConversionMetrics();
        $topCategories = SmartMatchingAnalyticsService::getTopCategories(15);

        View::render('admin/smart-matching/analytics', [
            'weekly_trends' => $weeklyTrends,
            'daily_trends' => $dailyTrends,
            'score_distribution' => $scoreDistribution,
            'distance_distribution' => $distanceDistribution,
            'conversion_metrics' => $conversionMetrics,
            'top_categories' => $topCategories,
            'page_title' => 'Matching Analytics',
        ]);
    }

    /**
     * Algorithm configuration page
     */
    public function configuration(): void
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();

        // Handle POST - save configuration
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrDie();

            // Get current tenant config
            $tenant = Database::query(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            )->fetch();

            $config = $tenant && $tenant['configuration']
                ? json_decode($tenant['configuration'], true)
                : [];

            // Update smart matching config
            $config['algorithms'] = $config['algorithms'] ?? [];
            $config['algorithms']['smart_matching'] = [
                'enabled' => isset($_POST['enabled']),
                'broker_approval_enabled' => isset($_POST['broker_approval_enabled']),
                'max_distance_km' => (int) ($_POST['max_distance_km'] ?? 50),
                'min_match_score' => (int) ($_POST['min_match_score'] ?? 40),
                'hot_match_threshold' => (int) ($_POST['hot_match_threshold'] ?? 80),
                'weights' => [
                    'category' => (float) ($_POST['weight_category'] ?? 0.25),
                    'skill' => (float) ($_POST['weight_skill'] ?? 0.20),
                    'proximity' => (float) ($_POST['weight_proximity'] ?? 0.25),
                    'freshness' => (float) ($_POST['weight_freshness'] ?? 0.10),
                    'reciprocity' => (float) ($_POST['weight_reciprocity'] ?? 0.15),
                    'quality' => (float) ($_POST['weight_quality'] ?? 0.05),
                ],
                'proximity' => [
                    'walking_km' => (int) ($_POST['proximity_walking'] ?? 5),
                    'local_km' => (int) ($_POST['proximity_local'] ?? 15),
                    'city_km' => (int) ($_POST['proximity_city'] ?? 30),
                    'regional_km' => (int) ($_POST['proximity_regional'] ?? 50),
                    'max_km' => (int) ($_POST['proximity_max'] ?? 100),
                ],
            ];

            // Save to database
            Database::query(
                "UPDATE tenants SET configuration = ? WHERE id = ?",
                [json_encode($config), $tenantId]
            );

            // Clear engine cache so new config takes effect
            SmartMatchingEngine::clearCache();

            $_SESSION['flash_success'] = 'Smart Matching configuration saved successfully!';
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/smart-matching/configuration');
            exit;
        }

        // GET - show form
        $config = SmartMatchingEngine::getConfig();
        $categories = Category::all();

        View::render('admin/smart-matching/configuration', [
            'config' => $config,
            'categories' => $categories,
            'page_title' => 'Matching Configuration',
        ]);
    }

    /**
     * Clear match cache
     */
    public function clearCache(): void
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();

        try {
            Database::query("DELETE FROM match_cache WHERE tenant_id = ?", [$tenantId]);
            SmartMatchingEngine::clearCache();

            $_SESSION['flash_success'] = 'Match cache cleared successfully!';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to clear cache: ' . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/smart-matching');
        exit;
    }

    /**
     * Warm up cache for active users
     */
    public function warmupCache(): void
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        try {
            $result = SmartMatchingEngine::warmUpCache(100);

            $_SESSION['flash_success'] = sprintf(
                'Cache warmed up: %d users processed, %d matches cached.',
                $result['processed'],
                $result['cached']
            );
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to warm up cache: ' . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/smart-matching');
        exit;
    }

    /**
     * Run batch geocoding
     */
    public function runGeocoding(): void
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        try {
            $userResults = GeocodingService::batchGeocodeUsers(50);
            $listingResults = GeocodingService::batchGeocodeListings(50);

            $_SESSION['flash_success'] = sprintf(
                'Geocoding complete: Users (%d processed, %d success), Listings (%d processed, %d success)',
                $userResults['processed'],
                $userResults['success'],
                $listingResults['processed'],
                $listingResults['success']
            );
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Geocoding error: ' . $e->getMessage();
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/smart-matching');
        exit;
    }

    /**
     * API: Get real-time stats
     */
    public function apiStats(): void
    {
        $this->requireAdmin();

        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'stats' => SmartMatchingAnalyticsService::getOverallStats(),
            'geocoding' => GeocodingService::getStats(),
        ]);
        exit;
    }
}
