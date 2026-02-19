<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Models\Category;
use Nexus\Services\MatchingService;
use Nexus\Services\SmartMatchingEngine;

/**
 * MatchController - Smart Matching Dashboard
 *
 * Provides the UI for the Multiverse-class Smart Matching Engine.
 * Features:
 * - Match dashboard with hot/good/all matches
 * - Match preferences management
 * - Match interaction tracking
 */
class MatchController
{
    /**
     * Main matches dashboard
     */
    public function index()
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        // Get matches by type
        $matchData = MatchingService::getMatchesByType($userId);

        // Get stats
        $stats = MatchingService::getStats($userId);

        // Get preferences for display
        $preferences = MatchingService::getPreferences($userId);

        View::render('matches/index', [
            'hot_matches' => array_values($matchData['hot']),
            'good_matches' => array_values($matchData['good']),
            'mutual_matches' => array_values($matchData['mutual']),
            'all_matches' => $matchData['all'],
            'stats' => $stats,
            'preferences' => $preferences,
            'page_title' => 'Smart Matches',
        ]);
    }

    /**
     * Hot matches only
     */
    public function hot()
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $matches = MatchingService::getHotMatches($userId, 20);

        View::render('matches/hot', [
            'matches' => $matches,
            'page_title' => 'Hot Matches',
        ]);
    }

    /**
     * Mutual matches only
     */
    public function mutual()
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        $matches = MatchingService::getMutualMatches($userId, 20);

        View::render('matches/mutual', [
            'matches' => $matches,
            'page_title' => 'Mutual Matches',
        ]);
    }

    /**
     * Match preferences page
     */
    public function preferences()
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $prefs = [
                'max_distance_km' => (int)($_POST['max_distance_km'] ?? 25),
                'min_match_score' => (int)($_POST['min_match_score'] ?? 50),
                'notification_frequency' => $_POST['notification_frequency'] ?? 'daily',
                'notify_hot_matches' => isset($_POST['notify_hot_matches']),
                'notify_mutual_matches' => isset($_POST['notify_mutual_matches']),
                'categories' => $_POST['categories'] ?? [],
            ];

            if (MatchingService::savePreferences($userId, $prefs)) {
                $_SESSION['flash_success'] = 'Match preferences saved successfully!';
            } else {
                $_SESSION['flash_error'] = 'Failed to save preferences. Please try again.';
            }

            header('Location: ' . TenantContext::getBasePath() . '/matches/preferences');
            exit;
        }

        // Get current preferences
        $preferences = MatchingService::getPreferences($userId);

        // Get categories for filter options
        $categories = Category::all();

        View::render('matches/preferences', [
            'preferences' => $preferences,
            'categories' => $categories,
            'page_title' => 'Match Preferences',
        ]);
    }

    /**
     * API: Get matches as JSON
     */
    public function api()
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'];
        $type = $_GET['type'] ?? 'all';
        $limit = min(50, (int)($_GET['limit'] ?? 20));

        try {
            switch ($type) {
                case 'hot':
                    $matches = MatchingService::getHotMatches($userId, $limit);
                    break;
                case 'mutual':
                    $matches = MatchingService::getMutualMatches($userId, $limit);
                    break;
                default:
                    $matches = MatchingService::getSuggestionsForUser($userId, $limit);
            }

            echo json_encode([
                'success' => true,
                'data' => $matches,
                'count' => count($matches),
                'type' => $type,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch matches',
            ]);
        }
        exit;
    }

    /**
     * API: Record match interaction
     */
    public function interact()
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $userId = $_SESSION['user_id'];
        $data = json_decode(file_get_contents('php://input'), true);

        $listingId = (int)($data['listing_id'] ?? 0);
        $action = $data['action'] ?? '';
        $matchScore = $data['match_score'] ?? null;
        $distance = $data['distance'] ?? null;

        $validActions = ['viewed', 'contacted', 'saved', 'dismissed'];
        if (!$listingId || !in_array($action, $validActions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        $success = MatchingService::recordInteraction($userId, $listingId, $action, $matchScore, $distance);

        echo json_encode([
            'success' => $success,
            'action' => $action,
            'listing_id' => $listingId,
        ]);
        exit;
    }

    /**
     * API: Get match stats
     */
    public function stats()
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'];
        $stats = MatchingService::getStats($userId);

        echo json_encode([
            'success' => true,
            'stats' => $stats,
        ]);
        exit;
    }

    /**
     * Debug: Show match score breakdown (dev only)
     */
    public function debug()
    {
        $this->requireAuth();

        // Only allow in development
        if (!defined('NEXUS_DEBUG') || !NEXUS_DEBUG) {
            http_response_code(403);
            echo "Debug mode not enabled";
            exit;
        }

        $userId = $_SESSION['user_id'];
        $listingId = (int)($_GET['listing'] ?? 0);

        if (!$listingId) {
            // Show all matches with debug info
            $matches = MatchingService::getSuggestionsForUser($userId, 10);

            echo "<h1>Match Debug for User #{$userId}</h1>";
            echo "<pre>";
            foreach ($matches as $match) {
                echo "Listing #{$match['id']}: {$match['title']}\n";
                echo "  Score: {$match['match_score']}%\n";
                echo "  Distance: " . ($match['distance_km'] ?? 'N/A') . " km\n";
                echo "  Type: {$match['match_type']}\n";
                echo "  Reasons: " . implode(', ', $match['match_reasons'] ?? []) . "\n";
                if (isset($match['match_breakdown'])) {
                    echo "  Breakdown:\n";
                    foreach ($match['match_breakdown'] as $key => $value) {
                        echo "    - {$key}: " . round($value, 3) . "\n";
                    }
                }
                echo "\n";
            }
            echo "</pre>";
        }
        exit;
    }

    /**
     * Require authentication
     */
    private function requireAuth()
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }
}
