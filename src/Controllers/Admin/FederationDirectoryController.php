<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationDirectoryService;
use Nexus\Services\FederationPartnershipService;
use Nexus\Services\FederationAuditService;

/**
 * Federation Directory Controller
 *
 * Allows tenant admins to:
 * - Discover other timebanks available for partnership
 * - View timebank profiles
 * - Request partnerships
 * - Manage their own directory listing
 */
class FederationDirectoryController
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
     * Directory listing - discover other timebanks
     */
    public function index()
    {
        $tenantId = TenantContext::getId();

        // Check if federation is available
        $systemEnabled = FederationFeatureService::isGloballyEnabled();
        $isWhitelisted = FederationFeatureService::isTenantWhitelisted($tenantId);

        if (!$systemEnabled || !$isWhitelisted) {
            View::render('admin/federation/directory-unavailable', [
                'systemEnabled' => $systemEnabled,
                'isWhitelisted' => $isWhitelisted,
                'pageTitle' => 'Federation Directory'
            ]);
            return;
        }

        // Get filters from query string
        $filters = [
            'search' => $_GET['q'] ?? '',
            'region' => $_GET['region'] ?? '',
            'category' => $_GET['category'] ?? '',
            'exclude_partnered' => isset($_GET['available_only']),
        ];

        // Get discoverable timebanks
        $timebanks = FederationDirectoryService::getDiscoverableTimebanks($tenantId, $filters);

        // Get filter options
        $regions = FederationDirectoryService::getAvailableRegions();
        $categories = FederationDirectoryService::getAvailableCategories();

        // Get current tenant's directory profile
        $myProfile = FederationDirectoryService::getTimebankProfile($tenantId);

        View::render('admin/federation/directory', [
            'timebanks' => $timebanks,
            'regions' => $regions,
            'categories' => $categories,
            'filters' => $filters,
            'myProfile' => $myProfile,
            'pageTitle' => 'Discover Partner Timebanks'
        ]);
    }

    /**
     * View a single timebank's profile
     */
    public function show($timebankId)
    {
        $tenantId = TenantContext::getId();
        $timebankId = (int)$timebankId;

        if ($timebankId === $tenantId) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/federation/directory/profile');
            exit;
        }

        // Get timebank profile
        $timebank = FederationDirectoryService::getTimebankProfile($timebankId);

        if (!$timebank) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // Check partnership status
        $partnership = FederationPartnershipService::getPartnership($tenantId, $timebankId);

        // Get enabled features for this timebank
        $features = [
            'profiles' => FederationFeatureService::isTenantFeatureEnabled('tenant_profiles_enabled', $timebankId),
            'listings' => FederationFeatureService::isTenantFeatureEnabled('tenant_listings_enabled', $timebankId),
            'messaging' => FederationFeatureService::isTenantFeatureEnabled('tenant_messaging_enabled', $timebankId),
            'transactions' => FederationFeatureService::isTenantFeatureEnabled('tenant_transactions_enabled', $timebankId),
            'events' => FederationFeatureService::isTenantFeatureEnabled('tenant_events_enabled', $timebankId),
            'groups' => FederationFeatureService::isTenantFeatureEnabled('tenant_groups_enabled', $timebankId),
        ];

        View::render('admin/federation/directory-profile', [
            'timebank' => $timebank,
            'partnership' => $partnership,
            'features' => $features,
            'pageTitle' => $timebank['name']
        ]);
    }

    /**
     * Edit own directory profile
     */
    public function profile()
    {
        $tenantId = TenantContext::getId();

        // Get current profile
        $profile = FederationDirectoryService::getTimebankProfile($tenantId);

        View::render('admin/federation/directory-my-profile', [
            'profile' => $profile,
            'pageTitle' => 'My Directory Listing'
        ]);
    }

    /**
     * Update own directory profile (AJAX)
     */
    public function updateProfile()
    {
        header('Content-Type: application/json');

        $tenantId = TenantContext::getId();

        $input = json_decode(file_get_contents('php://input'), true);

        // Validate and sanitize input
        $data = [];

        if (isset($input['description'])) {
            $data['federation_public_description'] = substr(trim($input['description']), 0, 2000);
        }

        if (isset($input['categories'])) {
            // Store as comma-separated string
            if (is_array($input['categories'])) {
                $data['federation_categories'] = implode(',', array_map('trim', $input['categories']));
            } else {
                $data['federation_categories'] = trim($input['categories']);
            }
        }

        if (isset($input['region'])) {
            $data['federation_region'] = substr(trim($input['region']), 0, 100);
        }

        if (isset($input['contact_email'])) {
            $email = filter_var($input['contact_email'], FILTER_VALIDATE_EMAIL);
            $data['federation_contact_email'] = $email ?: null;
        }

        if (isset($input['contact_name'])) {
            $data['federation_contact_name'] = substr(trim($input['contact_name']), 0, 200);
        }

        if (isset($input['show_member_count'])) {
            $data['federation_member_count_public'] = $input['show_member_count'] ? 1 : 0;
        }

        if (isset($input['discoverable'])) {
            $data['federation_discoverable'] = $input['discoverable'] ? 1 : 0;
        }

        if (empty($data)) {
            echo json_encode(['success' => false, 'error' => 'No valid fields to update']);
            return;
        }

        $result = FederationDirectoryService::updateDirectoryProfile($tenantId, $data);

        if ($result) {
            // Log the update
            FederationAuditService::log(
                'directory_profile_updated',
                $tenantId,
                null,
                $_SESSION['user_id'],
                ['fields_updated' => array_keys($data)]
            );

            echo json_encode(['success' => true, 'message' => 'Profile updated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update profile']);
        }
    }

    /**
     * Request partnership from directory (AJAX)
     */
    public function requestPartnership()
    {
        header('Content-Type: application/json');

        $tenantId = TenantContext::getId();

        // Verify federation is enabled
        if (!FederationFeatureService::isTenantFederationEnabled($tenantId)) {
            echo json_encode(['success' => false, 'error' => 'Federation is not enabled for your timebank']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $targetTenantId = (int)($input['target_tenant_id'] ?? 0);
        $federationLevel = (int)($input['federation_level'] ?? 2);
        $message = $input['message'] ?? null;

        if ($targetTenantId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid timebank selected']);
            return;
        }

        if ($targetTenantId === $tenantId) {
            echo json_encode(['success' => false, 'error' => 'Cannot partner with yourself']);
            return;
        }

        // Check target is whitelisted
        if (!FederationFeatureService::isTenantWhitelisted($targetTenantId)) {
            echo json_encode(['success' => false, 'error' => 'Selected timebank is not available for federation']);
            return;
        }

        $result = FederationPartnershipService::requestPartnership(
            $tenantId,
            $targetTenantId,
            $_SESSION['user_id'],
            $federationLevel,
            $message
        );

        echo json_encode($result);
    }

    /**
     * API endpoint for AJAX directory search
     */
    public function api()
    {
        header('Content-Type: application/json');

        $tenantId = TenantContext::getId();

        if (!FederationFeatureService::isTenantWhitelisted($tenantId)) {
            echo json_encode(['error' => 'Not authorized', 'timebanks' => []]);
            return;
        }

        $filters = [
            'search' => $_GET['q'] ?? '',
            'region' => $_GET['region'] ?? '',
            'category' => $_GET['category'] ?? '',
            'exclude_partnered' => isset($_GET['available_only']),
            'limit' => min((int)($_GET['limit'] ?? 50), 100),
        ];

        $timebanks = FederationDirectoryService::getDiscoverableTimebanks($tenantId, $filters);

        echo json_encode([
            'success' => true,
            'timebanks' => $timebanks,
            'count' => count($timebanks),
        ]);
    }
}
