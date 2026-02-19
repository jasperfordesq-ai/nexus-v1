<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\SuperAdmin;

use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Middleware\SuperPanelAccess;
use Nexus\Services\TenantVisibilityService;
use Nexus\Services\SuperAdminAuditService;
use Nexus\Models\Tenant;

/**
 * Super Admin Bulk Operations Controller
 *
 * Handles bulk operations on users and tenants.
 */
class BulkController
{
    public function __construct()
    {
        SuperPanelAccess::handle();
    }

    /**
     * Display bulk operations page
     */
    public function index()
    {
        $access = SuperPanelAccess::getAccess();
        $tenants = TenantVisibilityService::getTenantList();
        $hubTenants = array_filter($tenants, fn($t) => $t['allows_subtenants']);

        View::render('super-admin/bulk/index', [
            'access' => $access,
            'tenants' => $tenants,
            'hubTenants' => array_values($hubTenants),
            'pageTitle' => 'Bulk Operations'
        ]);
    }

    /**
     * Bulk move users to a different tenant
     */
    public function moveUsers()
    {
        Csrf::verifyOrDie();

        $userIds = $_POST['user_ids'] ?? [];
        $targetTenantId = (int)($_POST['target_tenant_id'] ?? 0);
        $grantSuperAdmin = isset($_POST['grant_super_admin']);

        if (empty($userIds) || !is_array($userIds)) {
            $this->redirectWithError('/super-admin/bulk', 'No users selected');
            return;
        }

        if (!$targetTenantId || !SuperPanelAccess::canAccessTenant($targetTenantId)) {
            $this->redirectWithError('/super-admin/bulk', 'Invalid target tenant');
            return;
        }

        $targetTenant = Tenant::find($targetTenantId);
        if (!$targetTenant) {
            $this->redirectWithError('/super-admin/bulk', 'Target tenant not found');
            return;
        }

        // If granting super admin, tenant must allow sub-tenants
        if ($grantSuperAdmin && !$targetTenant['allows_subtenants']) {
            $this->redirectWithError('/super-admin/bulk',
                'Cannot grant Super Admin: target tenant is not a Hub');
            return;
        }

        $movedCount = 0;
        $errors = [];
        $movedUsers = [];

        foreach ($userIds as $userId) {
            $userId = (int)$userId;

            // Get user info
            $user = Database::query(
                "SELECT id, first_name, last_name, email, tenant_id FROM users WHERE id = ?",
                [$userId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                $errors[] = "User ID {$userId} not found";
                continue;
            }

            // Check permission to manage source tenant
            if (!SuperPanelAccess::canManageTenant((int)$user['tenant_id'])) {
                $errors[] = "Cannot modify user: " . ($user['first_name'] ?? $userId);
                continue;
            }

            try {
                // Move user and all their content to new tenant
                if (!\Nexus\Models\User::moveTenant($userId, $targetTenantId)) {
                    $errors[] = "Failed to move: " . ($user['first_name'] ?? $userId);
                    continue;
                }

                // Grant super admin if requested
                if ($grantSuperAdmin) {
                    Database::query(
                        "UPDATE users SET is_tenant_super_admin = 1, role = 'tenant_admin' WHERE id = ?",
                        [$userId]
                    );
                } else {
                    // Revoke super admin if moving to non-hub tenant
                    if (!$targetTenant['allows_subtenants']) {
                        Database::query("UPDATE users SET is_tenant_super_admin = 0 WHERE id = ?", [$userId]);
                    }
                }

                $movedCount++;
                $movedUsers[] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

            } catch (\Exception $e) {
                $errors[] = "Failed to move: " . ($user['first_name'] ?? $userId);
            }
        }

        // Log bulk action
        SuperAdminAuditService::log(
            'bulk_users_moved',
            'bulk',
            null,
            "Bulk move to {$targetTenant['name']}",
            ['user_count' => count($userIds)],
            [
                'target_tenant_id' => $targetTenantId,
                'moved_count' => $movedCount,
                'grant_super_admin' => $grantSuperAdmin
            ],
            "Moved {$movedCount} users to '{$targetTenant['name']}'" .
            ($grantSuperAdmin ? ' with Super Admin privileges' : '')
        );

        if ($movedCount > 0) {
            $_SESSION['flash_success'] = "Successfully moved {$movedCount} user(s) to '{$targetTenant['name']}'";
        }

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode('; ', $errors);
        }

        header('Location: /super-admin/bulk');
    }

    /**
     * Bulk update tenant status (enable/disable)
     */
    public function updateTenants()
    {
        Csrf::verifyOrDie();

        $tenantIds = $_POST['tenant_ids'] ?? [];
        $action = $_POST['action'] ?? '';

        if (empty($tenantIds) || !is_array($tenantIds)) {
            $this->redirectWithError('/super-admin/bulk', 'No tenants selected');
            return;
        }

        if (!in_array($action, ['activate', 'deactivate', 'enable_hub', 'disable_hub'])) {
            $this->redirectWithError('/super-admin/bulk', 'Invalid action');
            return;
        }

        $updatedCount = 0;
        $errors = [];
        $updatedTenants = [];

        foreach ($tenantIds as $tenantId) {
            $tenantId = (int)$tenantId;

            // Cannot modify Master tenant
            if ($tenantId === 1) {
                $errors[] = "Cannot modify Master tenant";
                continue;
            }

            // Check permission
            if (!SuperPanelAccess::canManageTenant($tenantId)) {
                $errors[] = "Cannot modify tenant ID {$tenantId}";
                continue;
            }

            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $errors[] = "Tenant ID {$tenantId} not found";
                continue;
            }

            try {
                switch ($action) {
                    case 'activate':
                        Database::query("UPDATE tenants SET is_active = 1 WHERE id = ?", [$tenantId]);
                        break;

                    case 'deactivate':
                        Database::query("UPDATE tenants SET is_active = 0 WHERE id = ?", [$tenantId]);
                        break;

                    case 'enable_hub':
                        Database::query(
                            "UPDATE tenants SET allows_subtenants = 1, max_depth = 2 WHERE id = ?",
                            [$tenantId]
                        );
                        break;

                    case 'disable_hub':
                        Database::query(
                            "UPDATE tenants SET allows_subtenants = 0, max_depth = 0 WHERE id = ?",
                            [$tenantId]
                        );
                        break;
                }

                $updatedCount++;
                $updatedTenants[] = $tenant['name'];

            } catch (\Exception $e) {
                $errors[] = "Failed to update: {$tenant['name']}";
            }
        }

        // Log bulk action
        $actionLabels = [
            'activate' => 'Activated',
            'deactivate' => 'Deactivated',
            'enable_hub' => 'Hub Enabled',
            'disable_hub' => 'Hub Disabled'
        ];

        SuperAdminAuditService::log(
            'bulk_tenants_updated',
            'bulk',
            null,
            "Bulk {$action}",
            ['tenant_count' => count($tenantIds)],
            ['action' => $action, 'updated_count' => $updatedCount],
            "{$actionLabels[$action]} {$updatedCount} tenant(s)"
        );

        if ($updatedCount > 0) {
            $_SESSION['flash_success'] = "{$actionLabels[$action]} {$updatedCount} tenant(s)";
        }

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode('; ', $errors);
        }

        header('Location: /super-admin/bulk');
    }

    /**
     * API: Get users for bulk selection
     */
    public function apiGetUsers()
    {
        header('Content-Type: application/json');

        $tenantId = !empty($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;
        $search = $_GET['q'] ?? '';

        $users = TenantVisibilityService::getUserList([
            'tenant_id' => $tenantId,
            'search' => $search,
            'limit' => 100
        ]);

        echo json_encode([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * Helper: Redirect with error
     */
    private function redirectWithError(string $url, string $error): void
    {
        $_SESSION['flash_error'] = $error;
        header('Location: ' . $url);
        exit;
    }
}
