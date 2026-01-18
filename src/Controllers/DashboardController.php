<?php

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

        // Get active tab from query parameter
        $activeTab = $_GET['tab'] ?? 'overview';

        View::render('dashboard', [
            'user' => $user,
            'my_listings' => $my_listings,
            'activity_feed' => $activity_feed,
            'suggested_matches' => $suggested_matches,
            'pending_proposals' => $pending_proposals,
            'myGroups' => $myGroups,
            'myEvents' => $myEvents,
            'notifications' => $notifications,
            'notifSettings' => $notifSettings,
            'activeTab' => $activeTab
        ]);
    }
}
