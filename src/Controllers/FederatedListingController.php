<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationPartnershipService;

/**
 * Federated Listing Controller
 *
 * Browse and search listings from partner timebanks
 */
class FederatedListingController
{
    /**
     * Federated listing directory
     */
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];

        // Check if federation is enabled
        $federationEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        if (!$federationEnabled) {
            View::render('federation/not-available', [
                'pageTitle' => 'Federation Not Available'
            ]);
            return;
        }

        // Get active partnerships with listings enabled
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $activePartnerships = array_filter($partnerships, fn($p) => $p['status'] === 'active' && $p['listings_enabled']);

        // Get partner tenant IDs
        $partnerTenantIds = [];
        foreach ($activePartnerships as $p) {
            if ($p['tenant_id'] == $tenantId) {
                $partnerTenantIds[] = $p['partner_tenant_id'];
            } else {
                $partnerTenantIds[] = $p['tenant_id'];
            }
        }

        // Build filters
        $filters = [
            'search' => $_GET['q'] ?? '',
            'tenant_id' => isset($_GET['tenant']) ? (int)$_GET['tenant'] : null,
            'type' => $_GET['type'] ?? null,
            'category' => $_GET['category'] ?? null,
            'limit' => 30,
            'offset' => (int)($_GET['offset'] ?? 0),
        ];

        // Get federated listings
        $listings = $this->getFederatedListings($partnerTenantIds, $filters);

        // Get partner tenant info for filter dropdown
        $partnerTenants = $this->getPartnerTenantInfo($partnerTenantIds);

        // Get categories for filter
        $categories = $this->getCategories();

        // Get partner communities for scope switcher (if any)
        $partnerCommunities = array_map(fn($t) => [
            'id' => $t['id'],
            'name' => $t['name']
        ], $partnerTenants);

        $currentScope = $_GET['scope'] ?? 'all';

        // Use CivicOne wrapper if CivicOne layout is active
        $viewPath = (layout() === 'civicone')
            ? 'civicone/federation/listings'
            : 'federation/listings';

        View::render($viewPath, [
            'listings' => $listings,
            'partnerTenants' => $partnerTenants,
            'categories' => $categories,
            'filters' => $filters,
            'partnerships' => $activePartnerships,
            'partnerCommunities' => $partnerCommunities,
            'currentScope' => $currentScope,
            'pageTitle' => 'Federated Listings'
        ]);
    }

    /**
     * API endpoint for AJAX search
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
            echo json_encode(['error' => 'Federation not enabled', 'listings' => []]);
            exit;
        }

        // Get active partner tenant IDs with listings enabled
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $partnerTenantIds = [];
        foreach ($partnerships as $p) {
            if ($p['status'] === 'active' && $p['listings_enabled']) {
                $partnerTenantIds[] = ($p['tenant_id'] == $tenantId)
                    ? $p['partner_tenant_id']
                    : $p['tenant_id'];
            }
        }

        $filters = [
            'search' => $_GET['q'] ?? '',
            'tenant_id' => isset($_GET['tenant']) ? (int)$_GET['tenant'] : null,
            'type' => $_GET['type'] ?? null,
            'category' => $_GET['category'] ?? null,
            'limit' => min((int)($_GET['limit'] ?? 30), 50),
            'offset' => max(0, (int)($_GET['offset'] ?? 0)),
        ];

        $listings = $this->getFederatedListings($partnerTenantIds, $filters);

        echo json_encode([
            'success' => true,
            'listings' => $listings,
            'hasMore' => count($listings) >= $filters['limit'],
        ]);
        exit;
    }

    /**
     * View a federated listing
     */
    public function show($listingId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $viewerId = $_SESSION['user_id'];
        $listingId = (int)$listingId;

        // Get the listing with user and tenant info
        $listing = Database::query(
            "SELECT l.*, u.name as owner_name, u.avatar_url as owner_avatar,
                    u.id as owner_id, u.tenant_id as owner_tenant_id,
                    t.name as tenant_name, t.domain as tenant_domain,
                    c.name as category_name,
                    fus.service_reach, fus.messaging_enabled_federated
             FROM listings l
             INNER JOIN users u ON l.user_id = u.id
             INNER JOIN tenants t ON u.tenant_id = t.id
             LEFT JOIN categories c ON l.category_id = c.id
             LEFT JOIN federation_user_settings fus ON u.id = fus.user_id
             WHERE l.id = ?
             AND l.status = 'active'
             AND l.federated_visibility IN ('listed', 'bookable')
             AND (fus.federation_optin = 1 OR fus.federation_optin IS NULL)",
            [$listingId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$listing) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // Check if from a partner tenant
        $isPartner = false;
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        foreach ($partnerships as $p) {
            if ($p['status'] === 'active') {
                $partnerTenant = ($p['tenant_id'] == $tenantId) ? $p['partner_tenant_id'] : $p['tenant_id'];
                if ($partnerTenant == $listing['owner_tenant_id']) {
                    $isPartner = true;
                    break;
                }
            }
        }

        if (!$isPartner && $listing['owner_tenant_id'] != $tenantId) {
            http_response_code(403);
            View::render('errors/403', [
                'message' => 'You do not have permission to view this listing.'
            ]);
            return;
        }

        // Check if can message the owner
        $canMessageResult = \Nexus\Services\FederationGateway::canSendMessage(
            $viewerId,
            $tenantId,
            $listing['owner_id'],
            $listing['owner_tenant_id']
        );
        $canMessage = $listing['messaging_enabled_federated'] && $canMessageResult['allowed'];

        View::render('federation/listing-detail', [
            'listing' => $listing,
            'canMessage' => $canMessage,
            'pageTitle' => $listing['title'] ?? 'Listing'
        ]);
    }

    /**
     * Get federated listings from partner tenants
     */
    private function getFederatedListings(array $partnerTenantIds, array $filters): array
    {
        if (empty($partnerTenantIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($partnerTenantIds), '?'));
        $params = $partnerTenantIds;

        $sql = "SELECT l.id, l.title, l.description, l.type, l.price,
                       l.created_at, l.user_id as owner_id,
                       u.name as owner_name, u.avatar_url as owner_avatar,
                       u.tenant_id as owner_tenant_id,
                       t.name as tenant_name,
                       c.name as category_name,
                       fus.service_reach
                FROM listings l
                INNER JOIN users u ON l.user_id = u.id
                INNER JOIN tenants t ON u.tenant_id = t.id
                LEFT JOIN categories c ON l.category_id = c.id
                LEFT JOIN federation_user_settings fus ON u.id = fus.user_id
                WHERE u.tenant_id IN ({$placeholders})
                AND l.status = 'active'
                AND l.federated_visibility IN ('listed', 'bookable')
                AND (fus.federation_optin = 1 OR fus.user_id IS NULL)";

        // Apply tenant filter
        if (!empty($filters['tenant_id']) && in_array($filters['tenant_id'], $partnerTenantIds)) {
            $sql .= " AND u.tenant_id = ?";
            $params[] = $filters['tenant_id'];
        }

        // Apply type filter (offer/request)
        if (!empty($filters['type']) && in_array($filters['type'], ['offer', 'request'])) {
            $sql .= " AND l.type = ?";
            $params[] = $filters['type'];
        }

        // Apply category filter
        if (!empty($filters['category'])) {
            $sql .= " AND l.category_id = ?";
            $params[] = (int)$filters['category'];
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (l.title LIKE ? OR l.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $filters['limit'];
        $params[] = $filters['offset'];

        try {
            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("FederatedListingController::getFederatedListings error: " . $e->getMessage());
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

    /**
     * Get categories for filter
     */
    private function getCategories(): array
    {
        try {
            return Database::query(
                "SELECT DISTINCT c.id, c.name
                 FROM categories c
                 WHERE c.type IN ('offer', 'request', 'both')
                 ORDER BY c.name"
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
