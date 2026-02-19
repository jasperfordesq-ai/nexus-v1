<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\FederatedGroupService;
use Nexus\Services\FederationUserService;
use Nexus\Services\FederationFeatureService;

/**
 * FederatedGroupController
 *
 * Handles browsing and joining groups from partner timebanks.
 */
class FederatedGroupController
{
    /**
     * Display federated groups browser
     */
    public function index(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();

        // Check federation is enabled
        if (!$this->isFederationEnabled($tenantId)) {
            View::render('federation/not-available', [
                'pageTitle' => 'Federation Not Available'
            ]);
            return;
        }

        // Check if user has federation enabled
        $userSettings = FederationUserService::getUserSettings($userId);
        if (!$userSettings || !$userSettings['federation_optin']) {
            View::render('federation/groups-enable-required', [
                'pageTitle' => 'Enable Federation'
            ]);
            return;
        }

        // Build filters
        $filters = [
            'search' => trim($_GET['q'] ?? ''),
            'tenant_id' => isset($_GET['tenant']) ? (int)$_GET['tenant'] : null,
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));

        // Get groups data
        try {
            $result = FederatedGroupService::getPartnerGroups(
                $tenantId,
                $page,
                30,
                $filters['search'] ?: null,
                $filters['tenant_id']
            );

            // Get partner tenants for filter dropdown
            $partnerTenants = FederatedGroupService::getPartnerTenants($tenantId);

            // Get user's memberships to mark joined groups
            $userGroups = FederatedGroupService::getUserFederatedGroups($userId, $tenantId);
            $joinedGroupIds = array_column($userGroups, 'id');

            // Mark joined groups
            foreach ($result['groups'] as &$group) {
                $group['is_member'] = in_array($group['id'], $joinedGroupIds);
            }

            // Get partner communities for scope switcher (if any)
            $partnerCommunities = array_map(fn($t) => [
                'id' => $t['id'],
                'name' => $t['name']
            ], $partnerTenants);

            $currentScope = $_GET['scope'] ?? 'all';

            $viewPath = 'federation/groups';

            View::render($viewPath, [
                'groups' => $result['groups'],
                'partnerTenants' => $partnerTenants,
                'filters' => $filters,
                'partnerCommunities' => $partnerCommunities,
                'currentScope' => $currentScope,
                'pageTitle' => 'Federated Groups'
            ]);

        } catch (\Exception $e) {
            error_log('FederatedGroupController::index error: ' . $e->getMessage());
            View::render('federation/groups', [
                'groups' => [],
                'partnerTenants' => [],
                'filters' => $filters,
                'partnerCommunities' => [],
                'currentScope' => 'all',
                'pageTitle' => 'Federated Groups'
            ]);
        }
    }

    /**
     * API endpoint for groups data
     */
    public function api(): void
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();

        // Check federation settings
        $userSettings = FederationUserService::getUserSettings($userId);
        if (!$userSettings || !$userSettings['federation_optin']) {
            echo json_encode(['success' => false, 'error' => 'Federation not enabled']);
            return;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $search = trim($_GET['search'] ?? '');
        $partnerTenantId = !empty($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;

        try {
            $result = FederatedGroupService::getPartnerGroups(
                $tenantId,
                $page,
                12,
                $search ?: null,
                $partnerTenantId
            );

            // Get partner tenants for filter dropdown
            $tenants = FederatedGroupService::getPartnerTenants($tenantId);

            // Get user's memberships to mark joined groups
            $userGroups = FederatedGroupService::getUserFederatedGroups($userId, $tenantId);
            $joinedGroupIds = array_column($userGroups, 'id');

            // Mark joined groups
            foreach ($result['groups'] as &$group) {
                $group['is_member'] = in_array($group['id'], $joinedGroupIds);
            }

            echo json_encode([
                'success' => true,
                'groups' => $result['groups'],
                'tenants' => $tenants,
                'pagination' => [
                    'current_page' => $result['page'],
                    'total_pages' => $result['total_pages'],
                    'total' => $result['total'],
                    'per_page' => $result['per_page']
                ]
            ]);
        } catch (\Exception $e) {
            error_log('FederatedGroupController::api error: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Unable to load groups. Please ensure the federation database tables are configured.'
            ]);
        }
    }

    /**
     * Display single group detail
     */
    public function show(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();
        $groupTenantId = (int)($_GET['tenant'] ?? 0);

        if (!$groupTenantId) {
            $_SESSION['flash_error'] = 'Invalid group request';
            header('Location: ' . TenantContext::getBasePath() . '/federation/groups');
            exit;
        }

        // Check if user has federation enabled
        $userSettings = FederationUserService::getUserSettings($userId);
        if (!$userSettings || !$userSettings['federation_optin']) {
            View::render('federation/groups-enable-required', [
                'pageTitle' => 'Enable Federation'
            ]);
            return;
        }

        $group = FederatedGroupService::getPartnerGroup($id, $groupTenantId, $tenantId);

        if (!$group) {
            $_SESSION['flash_error'] = 'Group not found or not available';
            header('Location: ' . TenantContext::getBasePath() . '/federation/groups');
            exit;
        }

        // Check membership status
        $membership = FederatedGroupService::isFederatedMember($userId, $tenantId, $id);

        $canJoin = FederatedGroupService::canAccessPartnerGroups($tenantId, $groupTenantId);
        $isMember = $membership !== null;
        $membershipStatus = $membership['status'] ?? null;

        View::render('federation/group-detail', [
            'group' => $group,
            'canJoin' => $canJoin,
            'isMember' => $isMember,
            'membershipStatus' => $membershipStatus,
            'pageTitle' => $group['name'] ?? 'Group'
        ]);
    }

    /**
     * Join a federated group
     */
    public function join(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . TenantContext::getBasePath() . '/federation/groups');
            exit;
        }

        // CSRF validation
        \Nexus\Core\Csrf::verifyOrDie();

        $userId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();
        $groupTenantId = (int)($_POST['tenant_id'] ?? 0);

        if (!$groupTenantId) {
            $_SESSION['flash_error'] = 'Invalid group request';
            header('Location: ' . TenantContext::getBasePath() . '/federation/groups');
            exit;
        }

        // Check federation settings
        $userSettings = FederationUserService::getUserSettings($userId);
        if (!$userSettings || !$userSettings['federation_optin']) {
            $_SESSION['flash_error'] = 'You must enable federation to join groups from partner timebanks';
            header('Location: ' . TenantContext::getBasePath() . '/settings?section=federation');
            exit;
        }

        $result = FederatedGroupService::joinGroup($userId, $tenantId, $id, $groupTenantId);

        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }

        header('Location: ' . TenantContext::getBasePath() . "/federation/groups/{$id}?tenant={$groupTenantId}");
        exit;
    }

    /**
     * Leave a federated group
     */
    public function leave(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . TenantContext::getBasePath() . '/federation/groups');
            exit;
        }

        // CSRF validation
        \Nexus\Core\Csrf::verifyOrDie();

        $userId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();

        $result = FederatedGroupService::leaveGroup($userId, $tenantId, $id);

        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = $result['error'] ?? 'Failed to leave group';
        }

        header('Location: ' . TenantContext::getBasePath() . '/federation/groups');
        exit;
    }

    /**
     * Display user's federated group memberships
     */
    public function myGroups(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();

        // Check federation settings
        $userSettings = FederationUserService::getUserSettings($userId);
        if (!$userSettings || !$userSettings['federation_optin']) {
            View::render('federation/groups-enable-required', [
                'pageTitle' => 'Enable Federation'
            ]);
            return;
        }

        $groups = FederatedGroupService::getUserFederatedGroups($userId, $tenantId);

        View::render('federation/my-groups', [
            'groups' => $groups,
            'pageTitle' => 'My Federated Groups'
        ]);
    }

    /**
     * Check if federation is enabled for tenant
     */
    private function isFederationEnabled(int $tenantId): bool
    {
        return FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);
    }
}
