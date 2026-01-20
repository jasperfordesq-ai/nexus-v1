<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationGateway;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationPartnershipService;
use Nexus\Services\FederationUserService;
use Nexus\Services\FederationSearchService;
use Nexus\Services\ReviewService;

/**
 * Federated Member Controller
 *
 * Browse and search members from partner timebanks
 */
class FederatedMemberController
{
    /**
     * Federated member directory
     */
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];

        // Check if federation is enabled for this tenant
        $federationEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        if (!$federationEnabled) {
            View::render('federation/not-available', [
                'pageTitle' => 'Federation Not Available'
            ]);
            return;
        }

        // Get active partnerships with profiles enabled
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $activePartnerships = array_filter($partnerships, fn($p) => $p['status'] === 'active' && $p['profiles_enabled']);

        // Get partner tenant IDs
        $partnerTenantIds = [];
        foreach ($activePartnerships as $p) {
            if ($p['tenant_id'] == $tenantId) {
                $partnerTenantIds[] = $p['partner_tenant_id'];
            } else {
                $partnerTenantIds[] = $p['tenant_id'];
            }
        }

        // Build filters (including advanced search options)
        $filters = [
            'search' => $_GET['q'] ?? '',
            'tenant_id' => isset($_GET['tenant']) ? (int)$_GET['tenant'] : null,
            'service_reach' => $_GET['reach'] ?? null,
            'skills' => $_GET['skills'] ?? null,
            'location' => $_GET['location'] ?? null,
            'messaging_enabled' => isset($_GET['messaging']) && $_GET['messaging'] === '1',
            'transactions_enabled' => isset($_GET['transactions']) && $_GET['transactions'] === '1',
            'sort' => $_GET['sort'] ?? 'name',
            'limit' => 30,
            'offset' => (int)($_GET['offset'] ?? 0),
        ];

        // Use advanced search service
        $searchResult = FederationSearchService::searchMembers($partnerTenantIds, $filters);
        $members = $searchResult['members'];

        // Get partner tenant info for filter dropdown
        $partnerTenants = $this->getPartnerTenantInfo($partnerTenantIds);

        // Get search statistics
        $searchStats = FederationSearchService::getSearchStats($partnerTenantIds);

        // Get popular skills for filter suggestions
        $popularSkills = FederationSearchService::getAvailableSkills($partnerTenantIds, '', 15);

        // Get partner communities (simplified list for scope switcher)
        $partnerCommunities = array_map(fn($t) => [
            'id' => $t['id'],
            'name' => $t['name']
        ], $partnerTenants);

        $currentScope = $_GET['scope'] ?? 'all';

        View::render('federation/members', [
            'members' => $members,
            'partnerTenants' => $partnerTenants,
            'partnerCommunities' => $partnerCommunities,
            'currentScope' => $currentScope,
            'filters' => $filters,
            'partnerships' => $activePartnerships,
            'searchStats' => $searchStats,
            'popularSkills' => $popularSkills,
            'filtersApplied' => $searchResult['filters_applied'] ?? [],
            'pageTitle' => 'Federated Members'
        ]);
    }

    /**
     * API endpoint for infinite scroll / search
     */
    public function api()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $tenantId = TenantContext::getId();

        // Check federation enabled
        if (!FederationFeatureService::isTenantFederationEnabled($tenantId)) {
            echo json_encode(['error' => 'Federation not enabled', 'members' => []]);
            exit;
        }

        // Get active partner tenant IDs with profiles enabled
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $partnerTenantIds = [];
        foreach ($partnerships as $p) {
            if ($p['status'] === 'active' && $p['profiles_enabled']) {
                $partnerTenantIds[] = ($p['tenant_id'] == $tenantId)
                    ? $p['partner_tenant_id']
                    : $p['tenant_id'];
            }
        }

        $filters = [
            'search' => $_GET['q'] ?? '',
            'tenant_id' => isset($_GET['tenant']) ? (int)$_GET['tenant'] : null,
            'service_reach' => $_GET['reach'] ?? null,
            'skills' => $_GET['skills'] ?? null,
            'location' => $_GET['location'] ?? null,
            'messaging_enabled' => isset($_GET['messaging']) && $_GET['messaging'] === '1',
            'transactions_enabled' => isset($_GET['transactions']) && $_GET['transactions'] === '1',
            'sort' => $_GET['sort'] ?? 'name',
            'limit' => min((int)($_GET['limit'] ?? 30), 50),
            'offset' => max(0, (int)($_GET['offset'] ?? 0)),
        ];

        $searchResult = FederationSearchService::searchMembers($partnerTenantIds, $filters);

        echo json_encode([
            'success' => true,
            'members' => $searchResult['members'],
            'hasMore' => $searchResult['has_more'] ?? false,
            'filtersApplied' => $searchResult['filters_applied'] ?? [],
        ]);
        exit;
    }

    /**
     * API endpoint for skills autocomplete
     */
    public function skillsApi()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $tenantId = TenantContext::getId();

        if (!FederationFeatureService::isTenantFederationEnabled($tenantId)) {
            echo json_encode(['skills' => []]);
            exit;
        }

        $partnerTenantIds = $this->getPartnerTenantIds($tenantId);
        $query = $_GET['q'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 20), 50);

        $skills = FederationSearchService::getAvailableSkills($partnerTenantIds, $query, $limit);

        echo json_encode([
            'success' => true,
            'skills' => $skills,
        ]);
        exit;
    }

    /**
     * API endpoint for locations autocomplete
     */
    public function locationsApi()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $tenantId = TenantContext::getId();

        if (!FederationFeatureService::isTenantFederationEnabled($tenantId)) {
            echo json_encode(['locations' => []]);
            exit;
        }

        $partnerTenantIds = $this->getPartnerTenantIds($tenantId);
        $query = $_GET['q'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 20), 50);

        $locations = FederationSearchService::getAvailableLocations($partnerTenantIds, $query, $limit);

        echo json_encode([
            'success' => true,
            'locations' => $locations,
        ]);
        exit;
    }

    /**
     * Get partner tenant IDs for current tenant
     */
    private function getPartnerTenantIds(int $tenantId): array
    {
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $partnerTenantIds = [];

        foreach ($partnerships as $p) {
            if ($p['status'] === 'active' && $p['profiles_enabled']) {
                $partnerTenantIds[] = ($p['tenant_id'] == $tenantId)
                    ? $p['partner_tenant_id']
                    : $p['tenant_id'];
            }
        }

        return $partnerTenantIds;
    }

    /**
     * View a federated member's profile
     */
    public function show($memberId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $viewerId = $_SESSION['user_id'];
        $memberId = (int)$memberId;

        // Get the member
        $member = Database::query(
            "SELECT u.*,
                    TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as display_name,
                    t.name as tenant_name, t.domain as tenant_domain,
                    fus.show_skills_federated, fus.show_location_federated,
                    fus.messaging_enabled_federated, fus.transactions_enabled_federated,
                    fus.service_reach
             FROM users u
             INNER JOIN tenants t ON u.tenant_id = t.id
             INNER JOIN federation_user_settings fus ON u.id = fus.user_id
             WHERE u.id = ?
             AND u.status = 'active'
             AND fus.federation_optin = 1
             AND fus.profile_visible_federated = 1",
            [$memberId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$member) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // Check if we can view this profile (partnership check)
        if (!FederationGateway::canViewProfile($tenantId, $member['tenant_id'], $viewerId, $memberId)) {
            http_response_code(403);
            View::render('errors/403', [
                'message' => 'You do not have permission to view this profile.'
            ]);
            return;
        }

        // Mask fields based on user preferences
        if (!$member['show_skills_federated']) {
            $member['skills'] = null;
        }
        if (!$member['show_location_federated']) {
            $member['location'] = null;
            $member['latitude'] = null;
            $member['longitude'] = null;
        }

        // Get reviews and trust score for cross-tenant profile display
        $reviewData = FederationUserService::getFederatedReviews($memberId, $tenantId, 5);
        $trustScore = FederationUserService::getTrustScore($memberId);

        // Check if viewer has a pending review for this member
        $pendingReviewTransaction = null;
        $pendingReviews = ReviewService::getPendingReviews($viewerId);
        foreach ($pendingReviews as $pending) {
            if (($pending['other_party_id'] ?? null) == $memberId) {
                $pendingReviewTransaction = $pending['id'];
                break;
            }
        }

        View::render('federation/member-profile', [
            'member' => $member,
            'canMessage' => $member['messaging_enabled_federated'] && FederationGateway::canSendMessage($viewerId, $tenantId, $memberId, $member['tenant_id'])['allowed'],
            'canTransact' => $member['transactions_enabled_federated'] && FederationGateway::canPerformTransaction($viewerId, $tenantId, $memberId, $member['tenant_id'])['allowed'],
            'reviews' => $reviewData['reviews'] ?? [],
            'reviewStats' => $reviewData['stats'] ?? null,
            'trustScore' => $trustScore,
            'pendingReviewTransaction' => $pendingReviewTransaction,
            'pageTitle' => $member['display_name'] ?: ($member['name'] ?: 'Member Profile')
        ]);
    }

    /**
     * Get federated members from partner tenants
     */
    private function getFederatedMembers(array $partnerTenantIds, array $filters): array
    {
        if (empty($partnerTenantIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($partnerTenantIds), '?'));
        $params = $partnerTenantIds;

        $sql = "SELECT u.id,
                       TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as name,
                       u.avatar_url, u.bio, u.tenant_id,
                       t.name as tenant_name,
                       fus.service_reach,
                       CASE WHEN fus.show_location_federated = 1 THEN u.location ELSE NULL END as location,
                       CASE WHEN fus.show_skills_federated = 1 THEN u.skills ELSE NULL END as skills
                FROM users u
                INNER JOIN tenants t ON u.tenant_id = t.id
                INNER JOIN federation_user_settings fus ON u.id = fus.user_id
                WHERE u.tenant_id IN ({$placeholders})
                AND u.status = 'active'
                AND fus.federation_optin = 1
                AND fus.appear_in_federated_search = 1";

        // Apply tenant filter
        if (!empty($filters['tenant_id']) && in_array($filters['tenant_id'], $partnerTenantIds)) {
            $sql .= " AND u.tenant_id = ?";
            $params[] = $filters['tenant_id'];
        }

        // Apply service reach filter
        if (!empty($filters['service_reach'])) {
            if ($filters['service_reach'] === 'remote_ok') {
                $sql .= " AND fus.service_reach IN ('remote_ok', 'travel_ok')";
            } elseif ($filters['service_reach'] === 'travel_ok') {
                $sql .= " AND fus.service_reach = 'travel_ok'";
            }
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.bio LIKE ? OR u.skills LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY u.first_name, u.last_name LIMIT ? OFFSET ?";
        $params[] = $filters['limit'];
        $params[] = $filters['offset'];

        try {
            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("FederatedMemberController::getFederatedMembers error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get partner tenant info for filter dropdown
     */
    private function getPartnerTenantInfo(array $tenantIds): array
    {
        if (empty($tenantIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));

        try {
            return Database::query(
                "SELECT id, name, domain FROM tenants WHERE id IN ({$placeholders}) ORDER BY name",
                $tenantIds
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
