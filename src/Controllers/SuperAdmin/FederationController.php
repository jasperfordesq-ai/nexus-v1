<?php

namespace Nexus\Controllers\SuperAdmin;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Middleware\SuperPanelAccess;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationPartnershipService;
use Nexus\Services\FederationAuditService;
use Nexus\Core\Csrf;

/**
 * Super Admin Federation Controller
 *
 * Manages platform-wide federation settings including:
 * - Global kill switch
 * - System-level feature toggles
 * - Tenant whitelist management
 * - Partnership oversight
 * - Audit log viewing
 */
class FederationController
{
    public function __construct()
    {
        SuperPanelAccess::handle();
    }

    /**
     * Federation dashboard - overview of all federation activity
     */
    public function index()
    {
        $access = SuperPanelAccess::getAccess();

        // Get system control status
        $systemStatus = FederationFeatureService::getSystemControls();

        // Get partnership stats
        $partnershipStats = FederationPartnershipService::getStats();

        // Get whitelisted tenants count
        $whitelistedTenants = FederationFeatureService::getWhitelistedTenants();

        // Get recent audit log
        $recentAudit = FederationAuditService::getLog(['limit' => 20]);

        // Get recent critical events
        $criticalEvents = FederationAuditService::getRecentCritical(5);

        View::render('super-admin/federation/index', [
            'access' => $access,
            'systemStatus' => $systemStatus,
            'partnershipStats' => $partnershipStats,
            'whitelistedTenants' => $whitelistedTenants,
            'recentAudit' => $recentAudit,
            'criticalEvents' => $criticalEvents,
            'pageTitle' => 'Federation Control Center'
        ]);
    }

    /**
     * System controls page - master kill switch and feature toggles
     */
    public function systemControls()
    {
        $access = SuperPanelAccess::getAccess();

        if ($access['level'] !== 'master') {
            http_response_code(403);
            View::render('errors/403');
            return;
        }

        $systemStatus = FederationFeatureService::getSystemControls();

        View::render('super-admin/federation/system-controls', [
            'access' => $access,
            'systemStatus' => $systemStatus,
            'pageTitle' => 'Federation System Controls'
        ]);
    }

    /**
     * Update system controls (AJAX)
     */
    public function updateSystemControls()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $access = SuperPanelAccess::getAccess();

        if ($access['level'] !== 'master') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Master admin access required']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        try {
            $updates = [];
            $params = [];

            // Map input to database columns
            $allowedFields = [
                'federation_enabled' => 'federation_enabled',
                'whitelist_mode_enabled' => 'whitelist_mode_enabled',
                'max_federation_level' => 'max_federation_level',
                'cross_tenant_profiles_enabled' => 'cross_tenant_profiles_enabled',
                'cross_tenant_messaging_enabled' => 'cross_tenant_messaging_enabled',
                'cross_tenant_transactions_enabled' => 'cross_tenant_transactions_enabled',
                'cross_tenant_listings_enabled' => 'cross_tenant_listings_enabled',
                'cross_tenant_events_enabled' => 'cross_tenant_events_enabled',
                'cross_tenant_groups_enabled' => 'cross_tenant_groups_enabled',
            ];

            foreach ($allowedFields as $inputKey => $dbColumn) {
                if (isset($input[$inputKey])) {
                    $updates[] = "{$dbColumn} = ?";
                    // max_federation_level is an integer 0-4, not a boolean
                    if ($inputKey === 'max_federation_level') {
                        $params[] = max(0, min(4, (int)$input[$inputKey]));
                    } else {
                        $params[] = $input[$inputKey] ? 1 : 0;
                    }
                }
            }

            if (empty($updates)) {
                echo json_encode(['success' => false, 'error' => 'No valid fields to update']);
                return;
            }

            $updates[] = "updated_at = NOW()";
            $updates[] = "updated_by = ?";
            $params[] = $access['user_id'];

            $sql = "UPDATE federation_system_control SET " . implode(', ', $updates) . " WHERE id = 1";
            Database::query($sql, $params);

            // Clear cache
            FederationFeatureService::clearCache();

            // Audit log
            FederationAuditService::log(
                'system_controls_updated',
                null,
                null,
                $access['user_id'],
                ['changes' => $input],
                FederationAuditService::LEVEL_WARNING
            );

            echo json_encode(['success' => true, 'message' => 'System controls updated']);

        } catch (\Exception $e) {
            error_log("FederationController::updateSystemControls error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to update system controls']);
        }
    }

    /**
     * Emergency lockdown - immediately disable all federation
     */
    public function emergencyLockdown()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $access = SuperPanelAccess::getAccess();

        if ($access['level'] !== 'master') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Master admin access required']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $reason = $input['reason'] ?? 'Emergency lockdown triggered by admin';

        $result = FederationFeatureService::triggerEmergencyLockdown($access['user_id'], $reason);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Emergency lockdown activated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to activate lockdown']);
        }
    }

    /**
     * Lift emergency lockdown
     */
    public function liftLockdown()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $access = SuperPanelAccess::getAccess();

        if ($access['level'] !== 'master') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Master admin access required']);
            return;
        }

        $result = FederationFeatureService::liftEmergencyLockdown($access['user_id']);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Emergency lockdown lifted']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to lift lockdown']);
        }
    }

    /**
     * Whitelist management page
     */
    public function whitelist()
    {
        $access = SuperPanelAccess::getAccess();

        $whitelistedTenants = FederationFeatureService::getWhitelistedTenants();

        // Get all tenants for the add dropdown
        $allTenants = Database::query("
            SELECT id, name, domain FROM tenants
            WHERE id NOT IN (SELECT tenant_id FROM federation_tenant_whitelist)
            ORDER BY name
        ")->fetchAll(\PDO::FETCH_ASSOC);

        View::render('super-admin/federation/whitelist', [
            'access' => $access,
            'whitelistedTenants' => $whitelistedTenants,
            'availableTenants' => $allTenants,
            'pageTitle' => 'Federation Whitelist'
        ]);
    }

    /**
     * Add tenant to whitelist (AJAX)
     */
    public function addToWhitelist()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $access = SuperPanelAccess::getAccess();

        $input = json_decode(file_get_contents('php://input'), true);
        $tenantId = (int)($input['tenant_id'] ?? 0);
        $notes = $input['notes'] ?? null;

        if ($tenantId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid tenant ID']);
            return;
        }

        $result = FederationFeatureService::addToWhitelist($tenantId, $access['user_id'], $notes);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Tenant added to whitelist']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to add tenant to whitelist']);
        }
    }

    /**
     * Remove tenant from whitelist (AJAX)
     */
    public function removeFromWhitelist()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $access = SuperPanelAccess::getAccess();

        $input = json_decode(file_get_contents('php://input'), true);
        $tenantId = (int)($input['tenant_id'] ?? 0);

        if ($tenantId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid tenant ID']);
            return;
        }

        $result = FederationFeatureService::removeFromWhitelist($tenantId, $access['user_id']);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Tenant removed from whitelist']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to remove tenant from whitelist']);
        }
    }

    /**
     * Partnerships overview page
     */
    public function partnerships()
    {
        $access = SuperPanelAccess::getAccess();

        $partnerships = FederationPartnershipService::getAllPartnerships(null, 100);
        $stats = FederationPartnershipService::getStats();

        View::render('super-admin/federation/partnerships', [
            'access' => $access,
            'partnerships' => $partnerships,
            'stats' => $stats,
            'pageTitle' => 'Federation Partnerships'
        ]);
    }

    /**
     * Suspend a partnership (AJAX)
     */
    public function suspendPartnership()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $access = SuperPanelAccess::getAccess();

        $input = json_decode(file_get_contents('php://input'), true);
        $partnershipId = (int)($input['partnership_id'] ?? 0);
        $reason = $input['reason'] ?? 'Suspended by super admin';

        if ($partnershipId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid partnership ID']);
            return;
        }

        $result = FederationPartnershipService::suspendPartnership($partnershipId, $access['user_id'], $reason);
        echo json_encode($result);
    }

    /**
     * Terminate a partnership (AJAX)
     */
    public function terminatePartnership()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $access = SuperPanelAccess::getAccess();

        $input = json_decode(file_get_contents('php://input'), true);
        $partnershipId = (int)($input['partnership_id'] ?? 0);
        $reason = $input['reason'] ?? 'Terminated by super admin';

        if ($partnershipId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid partnership ID']);
            return;
        }

        $result = FederationPartnershipService::terminatePartnership($partnershipId, $access['user_id'], $reason);
        echo json_encode($result);
    }

    /**
     * Audit log page
     */
    public function auditLog()
    {
        $access = SuperPanelAccess::getAccess();

        $filters = [
            'category' => $_GET['category'] ?? null,
            'level' => $_GET['level'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'search' => $_GET['search'] ?? null,
            'limit' => 100,
        ];

        $logs = FederationAuditService::getLog($filters);
        $stats = FederationAuditService::getStats(30);

        View::render('super-admin/federation/audit-log', [
            'access' => $access,
            'logs' => $logs,
            'stats' => $stats,
            'filters' => $filters,
            'pageTitle' => 'Federation Audit Log'
        ]);
    }

    /**
     * Tenant federation features page (view/edit specific tenant)
     */
    public function tenantFeatures($tenantId)
    {
        $access = SuperPanelAccess::getAccess();

        $tenantId = (int)$tenantId;

        // Get tenant info
        $tenant = Database::query("SELECT * FROM tenants WHERE id = ?", [$tenantId])->fetch(\PDO::FETCH_ASSOC);

        if (!$tenant) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // Get tenant's federation features
        $features = FederationFeatureService::getAllTenantFeatures($tenantId);

        // Check if whitelisted
        $isWhitelisted = FederationFeatureService::isTenantWhitelisted($tenantId);

        // Get tenant's partnerships
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);

        View::render('super-admin/federation/tenant-features', [
            'access' => $access,
            'tenant' => $tenant,
            'features' => $features,
            'isWhitelisted' => $isWhitelisted,
            'partnerships' => $partnerships,
            'pageTitle' => 'Federation Settings: ' . $tenant['name']
        ]);
    }

    /**
     * Update tenant federation feature (AJAX)
     */
    public function updateTenantFeature()
    {
        header('Content-Type: application/json');
        Csrf::verifyOrDieJson();

        $access = SuperPanelAccess::getAccess();

        $input = json_decode(file_get_contents('php://input'), true);
        $tenantId = (int)($input['tenant_id'] ?? 0);
        $feature = $input['feature'] ?? '';
        $enabled = (bool)($input['enabled'] ?? false);

        if ($tenantId <= 0 || empty($feature)) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            return;
        }

        if ($enabled) {
            $result = FederationFeatureService::enableTenantFeature($feature, $tenantId);
        } else {
            $result = FederationFeatureService::disableTenantFeature($feature, $tenantId);
        }

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Feature updated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update feature']);
        }
    }
}
