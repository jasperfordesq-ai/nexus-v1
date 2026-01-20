<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationPartnershipService;
use Nexus\Services\FederationActivityService;

/**
 * Federation Hub Controller
 *
 * Master page for all federation features - Partner Timebanks
 */
class FederationHubController
{
    /**
     * Federation hub / landing page
     */
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];
        $basePath = TenantContext::getBasePath();

        // Check if federation is enabled
        $federationEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        if (!$federationEnabled) {
            View::render('federation/not-available', [
                'pageTitle' => 'Partner Timebanks Not Available'
            ]);
            return;
        }

        // Check if user has opted into federation
        $userFedSettings = Database::query(
            "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
            [$userId]
        )->fetch();
        $userOptedIn = $userFedSettings && $userFedSettings['federation_optin'];

        // Get active partnerships
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $activePartnerships = array_filter($partnerships, fn($p) => $p['status'] === 'active');
        $partnerCount = count($activePartnerships);

        // Get partner tenant details
        $partnerTenants = [];
        foreach ($activePartnerships as $p) {
            $partnerId = ($p['tenant_id'] == $tenantId) ? $p['partner_tenant_id'] : $p['tenant_id'];
            $tenant = Database::query("SELECT id, name, slug, og_image_url FROM tenants WHERE id = ?", [$partnerId])->fetch();
            if ($tenant) {
                $partnerTenants[] = [
                    'id' => $tenant['id'],
                    'name' => $tenant['name'],
                    'slug' => $tenant['slug'],
                    'logo_url' => $tenant['og_image_url'] ?? null,
                    'members_enabled' => $p['profiles_enabled'] ?? false,
                    'listings_enabled' => $p['listings_enabled'] ?? false,
                    'events_enabled' => $p['events_enabled'] ?? false,
                    'groups_enabled' => $p['groups_enabled'] ?? false,
                    'messaging_enabled' => $p['messaging_enabled'] ?? false,
                    'transactions_enabled' => $p['transactions_enabled'] ?? false,
                ];
            }
        }

        // Count federated content available
        $stats = $this->getFederationStats($tenantId, $activePartnerships);

        // Get feature availability across all partnerships
        $features = [
            'members' => false,
            'listings' => false,
            'events' => false,
            'groups' => false,
            'messages' => false,
            'transactions' => false,
        ];

        foreach ($activePartnerships as $p) {
            if ($p['profiles_enabled'] ?? false) $features['members'] = true;
            if ($p['listings_enabled'] ?? false) $features['listings'] = true;
            if ($p['events_enabled'] ?? false) $features['events'] = true;
            if ($p['groups_enabled'] ?? false) $features['groups'] = true;
            if ($p['messaging_enabled'] ?? false) $features['messages'] = true;
            if ($p['transactions_enabled'] ?? false) $features['transactions'] = true;
        }

        \Nexus\Core\SEO::setTitle('Partner Timebanks');
        \Nexus\Core\SEO::setDescription('Connect with members, listings, events, and groups from our partner timebanks.');

        // Get partner communities (simplified list for scope switcher)
        $partnerCommunities = array_map(fn($p) => [
            'id' => $p['id'],
            'name' => $p['name']
        ], $partnerTenants);

        $currentScope = $_GET['scope'] ?? 'all';

        View::render('federation/hub', [
            'pageTitle' => 'Partner Timebanks',
            'userOptedIn' => $userOptedIn,
            'partnerCount' => $partnerCount,
            'partnerTenants' => $partnerTenants,
            'partnerCommunities' => $partnerCommunities,
            'currentScope' => $currentScope,
            'features' => $features,
            'stats' => $stats,
            'basePath' => $basePath
        ]);
    }

    /**
     * Get federation statistics
     */
    private function getFederationStats($tenantId, $partnerships)
    {
        $stats = [
            'members' => 0,
            'listings' => 0,
            'events' => 0,
            'groups' => 0,
        ];

        // Get partner tenant IDs
        $partnerIds = [];
        foreach ($partnerships as $p) {
            $partnerId = ($p['tenant_id'] == $tenantId) ? $p['partner_tenant_id'] : $p['tenant_id'];
            $partnerIds[] = $partnerId;
        }

        if (empty($partnerIds)) {
            return $stats;
        }

        $placeholders = implode(',', array_fill(0, count($partnerIds), '?'));

        try {
            // Count federated members
            $membersResult = Database::query(
                "SELECT COUNT(DISTINCT u.id) as count
                 FROM users u
                 JOIN federation_user_settings fus ON u.id = fus.user_id
                 WHERE u.tenant_id IN ($placeholders)
                 AND fus.federation_optin = 1
                 AND fus.profile_visible_federated = 1",
                $partnerIds
            )->fetch();
            $stats['members'] = $membersResult['count'] ?? 0;

            // Count federated listings
            $listingsResult = Database::query(
                "SELECT COUNT(*) as count
                 FROM listings
                 WHERE tenant_id IN ($placeholders)
                 AND federated_visibility IN ('listed', 'bookable')
                 AND status = 'active'",
                $partnerIds
            )->fetch();
            $stats['listings'] = $listingsResult['count'] ?? 0;

            // Count federated events
            $eventsResult = Database::query(
                "SELECT COUNT(*) as count
                 FROM events
                 WHERE tenant_id IN ($placeholders)
                 AND federated_visibility IN ('listed', 'joinable')
                 AND start_time >= NOW()",
                $partnerIds
            )->fetch();
            $stats['events'] = $eventsResult['count'] ?? 0;

            // Count federated groups
            $groupsResult = Database::query(
                "SELECT COUNT(*) as count
                 FROM `groups`
                 WHERE tenant_id IN ($placeholders)
                 AND federated_visibility IN ('listed', 'joinable')",
                $partnerIds
            )->fetch();
            $stats['groups'] = $groupsResult['count'] ?? 0;

        } catch (\Exception $e) {
            error_log("Federation stats error: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Federation activity feed page
     * Shows recent messages, transactions, and new partners
     */
    public function activity()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];
        $basePath = TenantContext::getBasePath();

        // Check if federation is enabled
        $federationEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        if (!$federationEnabled) {
            View::render('federation/not-available', [
                'pageTitle' => 'Partner Timebanks Not Available'
            ]);
            return;
        }

        // Check if user has opted into federation
        $userFedSettings = Database::query(
            "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
            [$userId]
        )->fetch();
        $userOptedIn = $userFedSettings && $userFedSettings['federation_optin'];

        // Get activity feed
        $activities = FederationActivityService::getActivityFeed($userId, 50);

        // Get activity stats
        $stats = FederationActivityService::getActivityStats($userId);

        \Nexus\Core\SEO::setTitle('Federation Activity');
        \Nexus\Core\SEO::setDescription('View your recent federation activity including messages, transactions, and partner updates.');

        View::render('federation/activity', [
            'pageTitle' => 'Federation Activity',
            'activities' => $activities,
            'stats' => $stats,
            'userOptedIn' => $userOptedIn,
            'basePath' => $basePath
        ]);
    }

    /**
     * API endpoint for activity feed (for AJAX/infinite scroll)
     */
    public function activityApi()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $offset = (int)($_GET['offset'] ?? 0);
        $limit = min((int)($_GET['limit'] ?? 50), 100);
        $type = $_GET['type'] ?? null;

        $activities = FederationActivityService::getActivityFeed($userId, $limit, $offset);

        // Filter by type if specified
        if ($type && $type !== 'all') {
            $activities = array_filter($activities, fn($a) => $a['type'] === $type);
            $activities = array_values($activities);
        }

        echo json_encode([
            'success' => true,
            'activities' => $activities,
            'hasMore' => count($activities) >= $limit
        ]);
    }
}
