<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;

class DashboardController
{
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $user = \Nexus\Models\User::findById($userId);

        // Robust retry logic - handles temporary DB issues without destroying session
        if (!$user) {
            $maxRetries = 3;
            for ($i = 0; $i < $maxRetries && !$user; $i++) {
                usleep(200000); // 200ms delay between retries
                $user = \Nexus\Models\User::findById($userId);
            }
        }

        // If still not found after retries, log and redirect but DON'T destroy session
        // This prevents random logouts on transient DB issues
        if (!$user) {
            error_log("DashboardController::index - User ID {$userId} not found after retries. Possible DB issue or deleted user.");
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login?error=session_check_failed');
            exit;
        }

        $my_listings = \Nexus\Models\Listing::getForUser($userId);
        $activity_feed = \Nexus\Models\ActivityLog::getRecent(10);

        // Fetch user's groups (hubs)
        $myGroups = \Nexus\Models\Group::getUserGroups($userId);

        // Fetch user's upcoming events they're attending
        $myEvents = \Nexus\Models\Event::getAttending($userId);

        // Fetch notifications
        $notifications = \Nexus\Models\Notification::getLatest($userId, 50);
        $notifSettings = \Nexus\Models\Notification::getSettings($userId);

        // Smart Matching
        require_once __DIR__ . '/../Services/MatchingService.php';
        $suggested_matches = \Nexus\Services\MatchingService::getSuggestionsForUser($userId);

        // --- NEW: Governance Integration ---
        $db = Database::getConnection();
        $tenantId = \Nexus\Core\TenantContext::getId();
        // Fetch active proposals where user has NOT voted (tenant-isolated)
        $stmt = $db->prepare("
            SELECT p.*, g.name as group_name
            FROM proposals p
            JOIN groups g ON p.group_id = g.id
            WHERE p.status = 'active'
            AND g.tenant_id = ?
            AND p.deadline > NOW()
            AND NOT EXISTS (
                SELECT 1 FROM proposal_votes v
                WHERE v.proposal_id = p.id AND v.user_id = ?
            )
            LIMIT 3
        ");
        $stmt->execute([$tenantId, $userId]);
        $pending_proposals = $stmt->fetchAll();

        // Backward compatibility: Redirect old tab URLs to new dedicated pages
        if (isset($_GET['tab'])) {
            $basePath = \Nexus\Core\TenantContext::getBasePath();
            $tab = $_GET['tab'];

            $redirectMap = [
                'notifications' => '/dashboard/notifications',
                'groups' => '/dashboard/hubs',
                'hubs' => '/dashboard/hubs',
                'listings' => '/dashboard/listings',
                'wallet' => '/dashboard/wallet',
                'events' => '/dashboard/events',
                'overview' => '/dashboard', // Redirect overview to main dashboard
            ];

            if (isset($redirectMap[$tab])) {
                header('Location: ' . $basePath . $redirectMap[$tab], true, 301); // 301 Permanent Redirect
                exit;
            }
        }

        // No longer using tabs - render Overview page directly
        View::render('dashboard', [
            'user' => $user,
            'my_listings' => $my_listings,
            'activity_feed' => $activity_feed,
            'suggested_matches' => $suggested_matches,
            'pending_proposals' => $pending_proposals,
            'myGroups' => $myGroups,
            'myEvents' => $myEvents,
            'wallet_transactions' => [] // Will be populated in dedicated wallet page
        ]);
    }

    /**
     * Notifications page (dedicated route)
     */
    public function notifications()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $user = \Nexus\Models\User::findById($userId);

        if (!$user) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $notifications = \Nexus\Models\Notification::getLatest($userId, 50);
        $notifSettings = \Nexus\Models\Notification::getSettings($userId);
        $myGroups = \Nexus\Models\Group::getUserGroups($userId);

        View::render('dashboard/notifications', [
            'user' => $user,
            'notifications' => $notifications,
            'notifSettings' => $notifSettings,
            'myGroups' => $myGroups
        ]);
    }

    /**
     * My Hubs page (dedicated route)
     */
    public function hubs()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $user = \Nexus\Models\User::findById($userId);

        if (!$user) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $myGroups = \Nexus\Models\Group::getUserGroups($userId);

        View::render('dashboard/hubs', [
            'user' => $user,
            'myGroups' => $myGroups
        ]);
    }

    /**
     * My Listings page (dedicated route)
     */
    public function listings()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $user = \Nexus\Models\User::findById($userId);

        if (!$user) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $my_listings = \Nexus\Models\Listing::getForUser($userId);

        View::render('dashboard/listings', [
            'user' => $user,
            'my_listings' => $my_listings
        ]);
    }

    /**
     * Wallet page (dedicated route)
     */
    public function wallet()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $user = \Nexus\Models\User::findById($userId);

        if (!$user) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $wallet_transactions = \Nexus\Models\Transaction::getHistory($userId);

        View::render('dashboard/wallet', [
            'user' => $user,
            'wallet_transactions' => $wallet_transactions
        ]);
    }

    /**
     * My Events page (dedicated route)
     */
    public function events()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $user = \Nexus\Models\User::findById($userId);

        if (!$user) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $hosting = [];
        $attending = [];

        try {
            $hosting = \Nexus\Models\Event::getHosted($userId);
        } catch (\Exception $e) {
            error_log("Failed to fetch hosted events: " . $e->getMessage());
        }

        try {
            $attending = \Nexus\Models\Event::getAttending($userId);
        } catch (\Exception $e) {
            error_log("Failed to fetch attending events: " . $e->getMessage());
        }

        View::render('dashboard/events', [
            'user' => $user,
            'hosting' => $hosting,
            'attending' => $attending
        ]);
    }
}
