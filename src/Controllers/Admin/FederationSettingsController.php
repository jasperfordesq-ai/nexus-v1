<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationPartnershipService;
use Nexus\Services\FederationGateway;
use Nexus\Services\FederationAuditService;
use Nexus\Core\Csrf;

/**
 * Tenant Admin Federation Settings Controller
 *
 * Allows tenant admins to manage their own federation settings:
 * - Enable/disable federation for their tenant
 * - Manage partnership requests
 * - Configure what's shared with partners
 */
class FederationSettingsController
{
    public function __construct()
    {
        // Require admin role
        $role = $_SESSION['role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = in_array($role, ['super_admin', 'platform_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!isset($_SESSION['user_id']) || (!$isAdmin && !$isSuper && !$isAdminSession)) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }

    /**
     * Federation settings dashboard
     */
    public function index()
    {
        $tenantId = TenantContext::getId();

        // Check if federation is available for this tenant
        $systemEnabled = FederationFeatureService::isGloballyEnabled();
        $isWhitelisted = FederationFeatureService::isTenantWhitelisted($tenantId);

        // Get tenant's current federation features
        $features = FederationFeatureService::getAllTenantFeatures($tenantId);

        // Get federation status summary
        $statusSummary = FederationGateway::getStatusSummary($tenantId);

        // Get partnerships
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $pendingRequests = FederationPartnershipService::getPendingRequests($tenantId);

        View::render('admin/federation/index', [
            'systemEnabled' => $systemEnabled,
            'isWhitelisted' => $isWhitelisted,
            'features' => $features,
            'statusSummary' => $statusSummary,
            'partnerships' => $partnerships,
            'pendingRequests' => $pendingRequests,
            'pageTitle' => 'Federation Settings'
        ]);
    }

    /**
     * Update tenant federation feature (AJAX)
     */
    public function updateFeature()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $tenantId = TenantContext::getId();

        // Verify tenant is whitelisted
        if (!FederationFeatureService::isTenantWhitelisted($tenantId)) {
            echo json_encode(['success' => false, 'error' => 'Your timebank is not approved for federation']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $feature = $input['feature'] ?? '';
        $enabled = (bool)($input['enabled'] ?? false);

        if (empty($feature)) {
            echo json_encode(['success' => false, 'error' => 'Invalid feature']);
            return;
        }

        // Only allow tenant-level features to be changed
        $allowedFeatures = [
            FederationFeatureService::TENANT_FEDERATION_ENABLED,
            FederationFeatureService::TENANT_APPEAR_IN_DIRECTORY,
            FederationFeatureService::TENANT_PROFILES_ENABLED,
            FederationFeatureService::TENANT_MESSAGING_ENABLED,
            FederationFeatureService::TENANT_TRANSACTIONS_ENABLED,
            FederationFeatureService::TENANT_LISTINGS_ENABLED,
            FederationFeatureService::TENANT_EVENTS_ENABLED,
            FederationFeatureService::TENANT_GROUPS_ENABLED,
        ];

        if (!in_array($feature, $allowedFeatures)) {
            echo json_encode(['success' => false, 'error' => 'Invalid feature']);
            return;
        }

        if ($enabled) {
            $result = FederationFeatureService::enableTenantFeature($feature, $tenantId);
        } else {
            $result = FederationFeatureService::disableTenantFeature($feature, $tenantId);
        }

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Setting updated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update setting']);
        }
    }

    /**
     * Partnership management page
     */
    public function partnerships()
    {
        $tenantId = TenantContext::getId();

        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $pendingRequests = FederationPartnershipService::getPendingRequests($tenantId);
        $counterProposals = FederationPartnershipService::getCounterProposals($tenantId);
        $outgoingRequests = FederationPartnershipService::getOutgoingRequests($tenantId);

        // Get available tenants for partnership (those in directory)
        $availableTenants = $this->getAvailablePartnerTenants($tenantId);

        View::render('admin/federation/partnerships', [
            'partnerships' => $partnerships,
            'pendingRequests' => $pendingRequests,
            'counterProposals' => $counterProposals,
            'outgoingRequests' => $outgoingRequests,
            'availableTenants' => $availableTenants,
            'pageTitle' => 'Federation Partnerships'
        ]);
    }

    /**
     * Request a new partnership (AJAX)
     */
    public function requestPartnership()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $tenantId = TenantContext::getId();

        // Verify federation is enabled for this tenant
        if (!FederationFeatureService::isTenantFederationEnabled($tenantId)) {
            echo json_encode(['success' => false, 'error' => 'Federation is not enabled for your timebank']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $targetTenantId = (int)($input['target_tenant_id'] ?? 0);
        $federationLevel = (int)($input['federation_level'] ?? 1);
        $notes = $input['notes'] ?? null;

        if ($targetTenantId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid target timebank']);
            return;
        }

        if ($targetTenantId === $tenantId) {
            echo json_encode(['success' => false, 'error' => 'Cannot partner with yourself']);
            return;
        }

        $result = FederationPartnershipService::requestPartnership(
            $tenantId,
            $targetTenantId,
            $_SESSION['user_id'],
            $federationLevel,
            $notes
        );

        echo json_encode($result);
    }

    /**
     * Approve a partnership request (AJAX)
     */
    public function approvePartnership()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $tenantId = TenantContext::getId();

        $input = json_decode(file_get_contents('php://input'), true);
        $partnershipId = (int)($input['partnership_id'] ?? 0);

        if ($partnershipId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid partnership']);
            return;
        }

        // Verify this partnership is for our tenant
        $partnership = FederationPartnershipService::getPartnershipById($partnershipId);
        if (!$partnership || $partnership['partner_tenant_id'] !== $tenantId) {
            echo json_encode(['success' => false, 'error' => 'Partnership not found']);
            return;
        }

        $result = FederationPartnershipService::approvePartnership(
            $partnershipId,
            $_SESSION['user_id'],
            $input['permissions'] ?? []
        );

        echo json_encode($result);
    }

    /**
     * Reject a partnership request (AJAX)
     */
    public function rejectPartnership()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $tenantId = TenantContext::getId();

        $input = json_decode(file_get_contents('php://input'), true);
        $partnershipId = (int)($input['partnership_id'] ?? 0);
        $reason = $input['reason'] ?? null;

        if ($partnershipId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid partnership']);
            return;
        }

        // Verify this partnership is for our tenant
        $partnership = FederationPartnershipService::getPartnershipById($partnershipId);
        if (!$partnership || $partnership['partner_tenant_id'] !== $tenantId) {
            echo json_encode(['success' => false, 'error' => 'Partnership not found']);
            return;
        }

        $result = FederationPartnershipService::rejectPartnership(
            $partnershipId,
            $_SESSION['user_id'],
            $reason
        );

        echo json_encode($result);
    }

    /**
     * Update partnership permissions (AJAX)
     */
    public function updatePartnershipPermissions()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $tenantId = TenantContext::getId();

        $input = json_decode(file_get_contents('php://input'), true);
        $partnershipId = (int)($input['partnership_id'] ?? 0);
        $permissions = $input['permissions'] ?? [];

        if ($partnershipId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid partnership']);
            return;
        }

        // Verify this partnership involves our tenant
        $partnership = FederationPartnershipService::getPartnershipById($partnershipId);
        if (!$partnership ||
            ($partnership['tenant_id'] !== $tenantId && $partnership['partner_tenant_id'] !== $tenantId)) {
            echo json_encode(['success' => false, 'error' => 'Partnership not found']);
            return;
        }

        $result = FederationPartnershipService::updatePermissions(
            $partnershipId,
            $permissions,
            $_SESSION['user_id']
        );

        echo json_encode($result);
    }

    /**
     * Terminate a partnership (AJAX)
     */
    public function terminatePartnership()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $tenantId = TenantContext::getId();

        $input = json_decode(file_get_contents('php://input'), true);
        $partnershipId = (int)($input['partnership_id'] ?? 0);
        $reason = $input['reason'] ?? 'Terminated by tenant admin';

        if ($partnershipId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid partnership']);
            return;
        }

        // Verify this partnership involves our tenant
        $partnership = FederationPartnershipService::getPartnershipById($partnershipId);
        if (!$partnership ||
            ($partnership['tenant_id'] !== $tenantId && $partnership['partner_tenant_id'] !== $tenantId)) {
            echo json_encode(['success' => false, 'error' => 'Partnership not found']);
            return;
        }

        $result = FederationPartnershipService::terminatePartnership(
            $partnershipId,
            $_SESSION['user_id'],
            $reason
        );

        echo json_encode($result);
    }

    /**
     * Counter-propose a partnership request with different terms (AJAX)
     */
    public function counterPropose()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $tenantId = TenantContext::getId();

        $input = json_decode(file_get_contents('php://input'), true);
        $partnershipId = (int)($input['partnership_id'] ?? 0);
        $newLevel = (int)($input['federation_level'] ?? 2);
        $message = $input['message'] ?? null;
        $permissions = $input['permissions'] ?? [];

        if ($partnershipId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid partnership']);
            return;
        }

        // Verify this is an incoming request for our tenant
        $partnership = FederationPartnershipService::getPartnershipById($partnershipId);
        if (!$partnership || $partnership['partner_tenant_id'] !== $tenantId) {
            echo json_encode(['success' => false, 'error' => 'Partnership not found']);
            return;
        }

        if ($partnership['status'] !== 'pending') {
            echo json_encode(['success' => false, 'error' => 'Can only counter-propose pending requests']);
            return;
        }

        $result = FederationPartnershipService::counterPropose(
            $partnershipId,
            $_SESSION['user_id'],
            $newLevel,
            $permissions,
            $message
        );

        echo json_encode($result);
    }

    /**
     * Accept a counter-proposal (AJAX)
     */
    public function acceptCounterProposal()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $tenantId = TenantContext::getId();

        $input = json_decode(file_get_contents('php://input'), true);
        $partnershipId = (int)($input['partnership_id'] ?? 0);

        if ($partnershipId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid partnership']);
            return;
        }

        // Verify this is our outgoing request with a counter-proposal
        $partnership = FederationPartnershipService::getPartnershipById($partnershipId);
        if (!$partnership || $partnership['tenant_id'] !== $tenantId) {
            echo json_encode(['success' => false, 'error' => 'Partnership not found']);
            return;
        }

        if (empty($partnership['counter_proposed_at'])) {
            echo json_encode(['success' => false, 'error' => 'No counter-proposal to accept']);
            return;
        }

        $result = FederationPartnershipService::acceptCounterProposal(
            $partnershipId,
            $_SESSION['user_id']
        );

        echo json_encode($result);
    }

    /**
     * Withdraw an outgoing partnership request (AJAX)
     */
    public function withdrawRequest()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $tenantId = TenantContext::getId();

        $input = json_decode(file_get_contents('php://input'), true);
        $partnershipId = (int)($input['partnership_id'] ?? 0);

        if ($partnershipId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid partnership']);
            return;
        }

        // Verify this is our outgoing request
        $partnership = FederationPartnershipService::getPartnershipById($partnershipId);
        if (!$partnership || $partnership['tenant_id'] !== $tenantId) {
            echo json_encode(['success' => false, 'error' => 'Partnership not found']);
            return;
        }

        if ($partnership['status'] !== 'pending') {
            echo json_encode(['success' => false, 'error' => 'Can only withdraw pending requests']);
            return;
        }

        $result = FederationPartnershipService::terminatePartnership(
            $partnershipId,
            $_SESSION['user_id'],
            'Request withdrawn by sender'
        );

        echo json_encode($result);
    }

    /**
     * Get tenants available for partnership
     */
    private function getAvailablePartnerTenants(int $currentTenantId): array
    {
        try {
            // Get tenants that:
            // 1. Are whitelisted for federation
            // 2. Have federation enabled
            // 3. Appear in directory
            // 4. Are not already partnered with us
            return \Nexus\Core\Database::query("
                SELECT t.id, t.name, t.domain
                FROM tenants t
                INNER JOIN federation_tenant_whitelist fw ON t.id = fw.tenant_id
                INNER JOIN federation_tenant_features ftf ON t.id = ftf.tenant_id
                    AND ftf.feature_key = 'tenant_appear_in_directory'
                    AND ftf.is_enabled = 1
                WHERE t.id != ?
                AND t.id NOT IN (
                    SELECT CASE
                        WHEN tenant_id = ? THEN partner_tenant_id
                        ELSE tenant_id
                    END
                    FROM federation_partnerships
                    WHERE (tenant_id = ? OR partner_tenant_id = ?)
                    AND status IN ('pending', 'active', 'suspended')
                )
                ORDER BY t.name
            ", [$currentTenantId, $currentTenantId, $currentTenantId, $currentTenantId])
                ->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("FederationSettingsController::getAvailablePartnerTenants error: " . $e->getMessage());
            return [];
        }
    }
}
