<?php

namespace Nexus\Controllers;

use Nexus\Models\User;
use Nexus\Models\OrgMember;
use Nexus\Core\View;
use Nexus\Services\MemberRankingService;

/**
 * MemberController - Community Member Directory
 *
 * Uses CommunityRank algorithm exclusively for intelligent member ranking
 */
class MemberController
{
    public function index()
    {
        // Prevent browser back/forward cache (bfcache) from serving stale content
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');

        // Detect AJAX/API requests
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
        $isApi = (strpos($_SERVER['REQUEST_URI'], '/api') === 0) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        // Get current viewer ID for personalized ranking
        $viewerId = $_SESSION['user_id'] ?? null;

        // ============================================
        // SEARCH API ENDPOINT
        // ============================================
        if (isset($_GET['q']) && ($isAjax || $isApi || isset($_GET['ajax']))) {
            header('Content-Type: application/json');
            $results = User::search($_GET['q']);

            // Apply CommunityRank algorithm
            $results = MemberRankingService::rankMembers($results, $viewerId);

            echo json_encode(['data' => $results]);
            exit;
        }

        // ============================================
        // INFINITE SCROLL API ENDPOINT
        // ============================================
        if (isset($_GET['loadmore']) && ($isAjax || $isApi)) {
            header('Content-Type: application/json');

            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 30;
            $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

            // Use CommunityRank algorithm
            $filters = ['limit' => $limit, 'offset' => $offset];
            $query = MemberRankingService::buildRankedQuery($viewerId, $filters);
            $members = \Nexus\Core\Database::query($query['sql'], $query['params'])->fetchAll(\PDO::FETCH_ASSOC);

            $totalMembers = User::count();
            $hasMore = ($offset + $limit) < $totalMembers;

            echo json_encode([
                'data' => $members,
                'hasMore' => $hasMore,
                'total' => $totalMembers,
                'offset' => $offset,
                'limit' => $limit
            ]);
            exit;
        }

        // ============================================
        // NEARBY MEMBERS API ENDPOINT
        // ============================================
        if (isset($_GET['filter']) && $_GET['filter'] === 'nearby' && ($isAjax || $isApi)) {
            header('Content-Type: application/json');

            $radius = isset($_GET['radius']) ? (float)$_GET['radius'] : 25;
            $userId = $_SESSION['user_id'] ?? 0;

            if (!$userId) {
                echo json_encode(['data' => [], 'success' => false, 'error' => 'Login required']);
                exit;
            }

            // Get user's location and find nearby members
            $userCoords = $this->getUserCoordinates($userId);

            if ($userCoords) {
                $results = User::getNearby($userCoords['lat'], $userCoords['lon'], $radius, 50, $userId);

                // Apply CommunityRank algorithm to nearby results
                $results = MemberRankingService::rankMembers($results, $userId);

                echo json_encode([
                    'data' => $results,
                    'success' => true,
                    'userLocation' => $userCoords['location']
                ]);
            } else {
                echo json_encode(['data' => [], 'success' => false, 'error' => 'no_location']);
            }
            exit;
        }

        // ============================================
        // INITIAL PAGE LOAD
        // ============================================
        $limit = 30; // Initial load: 30 members
        $filters = ['limit' => $limit];

        // Use CommunityRank algorithm for initial member list with error handling
        try {
            $start = microtime(true);
            $query = MemberRankingService::buildRankedQuery($viewerId, $filters);
            $members = \Nexus\Core\Database::query($query['sql'], $query['params'])->fetchAll(\PDO::FETCH_ASSOC);
            $duration = microtime(true) - $start;

            // Performance monitoring
            if ($duration > 1.0) {
                error_log("MemberController: Slow ranking query ({$duration}s) for viewer {$viewerId}");
            }
        } catch (\Exception $e) {
            // Critical error - log and show friendly error
            error_log("MemberController: CommunityRank failed - " . $e->getMessage());
            \Nexus\Core\View::render('errors/500', [
                'message' => 'Unable to load member directory. Please try again later.',
                'error' => $e->getMessage()
            ]);
            exit;
        }

        // Cache user count for 5 minutes to reduce database load
        $tenantId = \Nexus\Core\TenantContext::getId();
        $cacheKey = "user_count_{$tenantId}";
        $cachedCount = $_SESSION[$cacheKey] ?? null;
        $cacheTime = $_SESSION["{$cacheKey}_time"] ?? 0;

        if ($cachedCount && (time() - $cacheTime) < 300) {
            $totalMembers = $cachedCount;
            error_log("MemberController: Using cached count: {$totalMembers} for tenant {$tenantId}");
        } else {
            $totalMembers = User::count();
            $_SESSION[$cacheKey] = $totalMembers;
            $_SESSION["{$cacheKey}_time"] = time();
            error_log("MemberController: Fresh count: {$totalMembers} for tenant {$tenantId}");
        }

        // Fetch organization leadership roles for displayed members
        $orgLeadership = [];
        if (!empty($members)) {
            $memberIds = array_column($members, 'id');
            try {
                $orgLeadership = OrgMember::getLeadershipRolesForUsers($memberIds);
            } catch (\Exception $e) {
                // Silently fail if org tables don't exist
                $orgLeadership = [];
            }
        }

        // Render view
        \Nexus\Core\View::render('members/index', [
            'members' => $members,
            'total_members' => $totalMembers,
            'orgLeadership' => $orgLeadership,
            'nearbyMode' => false // Nearby mode is handled client-side via API
        ]);
    }

    /**
     * Get user coordinates from their profile location via Mapbox geocoding
     */
    private function getUserCoordinates($userId)
    {
        try {
            $user = User::findById($userId);
            if (!$user || empty($user['location'])) {
                return null;
            }

            // First check if we have cached coordinates
            try {
                $coords = User::getCoordinates($userId);
                if ($coords && !empty($coords['latitude']) && !empty($coords['longitude'])) {
                    return [
                        'lat' => (float)$coords['latitude'],
                        'lon' => (float)$coords['longitude'],
                        'location' => $user['location']
                    ];
                }
            } catch (\Exception $e) {
                // Columns may not exist yet, continue to geocoding
                error_log("getCoordinates error: " . $e->getMessage());
            }

            // Geocode using Mapbox
            $token = getenv('MAPBOX_ACCESS_TOKEN');
            if (!$token) {
                // No Mapbox token, but user has location - return dummy coords for Ireland center
                return [
                    'lat' => 53.3498,  // Dublin latitude
                    'lon' => -6.2603,  // Dublin longitude
                    'location' => $user['location']
                ];
            }

            // Security: Sanitize location to prevent SSRF
            $location = preg_replace('/[\x00-\x1F\x7F]/', '', $user['location']);
            $location = trim($location);
            if (strlen($location) > 500 ||
                preg_match('/^(https?|ftp|file|data|javascript|vbscript):/i', $location) ||
                preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $location)) {
                return [
                    'lat' => 53.3498,
                    'lon' => -6.2603,
                    'location' => $user['location']
                ];
            }

            $url = "https://api.mapbox.com/geocoding/v5/mapbox.places/" . urlencode($location) . ".json?access_token=$token&country=ie&limit=1";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $resp = curl_exec($ch);

            if (curl_errno($ch)) {
                error_log("Mapbox curl error: " . curl_error($ch));
                curl_close($ch);
                return [
                    'lat' => 53.3498,
                    'lon' => -6.2603,
                    'location' => $user['location']
                ];
            }
            curl_close($ch);

            $json = json_decode($resp, true);

            if (empty($json['features'][0]['center'])) {
                return [
                    'lat' => 53.3498,
                    'lon' => -6.2603,
                    'location' => $user['location']
                ];
            }

            // Mapbox returns [longitude, latitude]
            $lon = $json['features'][0]['center'][0];
            $lat = $json['features'][0]['center'][1];

            // Cache the coordinates for future use
            try {
                User::updateCoordinates($userId, $lat, $lon);
            } catch (\Exception $e) {
                error_log("updateCoordinates error: " . $e->getMessage());
            }

            return [
                'lat' => $lat,
                'lon' => $lon,
                'location' => $user['location']
            ];
        } catch (\Exception $e) {
            error_log("getUserCoordinates error: " . $e->getMessage());
            return null;
        }
    }
}
