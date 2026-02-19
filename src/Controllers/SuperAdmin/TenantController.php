<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\SuperAdmin;

use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Middleware\SuperPanelAccess;
use Nexus\Services\TenantVisibilityService;
use Nexus\Services\TenantHierarchyService;
use Nexus\Models\Tenant;

/**
 * Super Admin Tenant Controller
 *
 * Handles tenant CRUD operations in the Super Admin Panel.
 * All operations are scoped to user's visibility in the hierarchy.
 */
class TenantController
{
    public function __construct()
    {
        SuperPanelAccess::handle();
    }

    /**
     * List all visible tenants
     */
    public function index()
    {
        $access = SuperPanelAccess::getAccess();

        $filters = [
            'search' => $_GET['search'] ?? null,
            'is_active' => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null,
            'allows_subtenants' => isset($_GET['hub']) ? 1 : null
        ];

        $tenants = TenantVisibilityService::getTenantList(array_filter($filters));
        $stats = TenantVisibilityService::getDashboardStats();

        View::render('super-admin/tenants/index', [
            'access' => $access,
            'tenants' => $tenants,
            'stats' => $stats,
            'filters' => $filters,
            'pageTitle' => 'Manage Tenants'
        ]);
    }

    /**
     * Visual hierarchy tree view with drag-and-drop
     */
    public function hierarchy()
    {
        $access = SuperPanelAccess::getAccess();
        $tenants = TenantVisibilityService::getTenantList();

        View::render('super-admin/tenants/hierarchy', [
            'access' => $access,
            'tenants' => $tenants,
            'pageTitle' => 'Tenant Hierarchy'
        ]);
    }

    /**
     * View single tenant details
     */
    public function show($id)
    {
        $tenantId = (int)$id;

        if (!SuperPanelAccess::canAccessTenant($tenantId)) {
            http_response_code(403);
            View::render('errors/403', ['message' => 'You cannot access this tenant']);
            return;
        }

        $tenant = TenantVisibilityService::getTenant($tenantId);
        if (!$tenant) {
            http_response_code(404);
            View::render('errors/404', ['message' => 'Tenant not found']);
            return;
        }

        $children = Tenant::getChildren($tenantId);
        $admins = TenantVisibilityService::getTenantAdmins($tenantId);
        $breadcrumb = Tenant::getBreadcrumb($tenantId);
        $access = SuperPanelAccess::getAccess();

        View::render('super-admin/tenants/show', [
            'access' => $access,
            'tenant' => $tenant,
            'children' => $children,
            'admins' => $admins,
            'breadcrumb' => $breadcrumb,
            'canManage' => SuperPanelAccess::canManageTenant($tenantId),
            'pageTitle' => $tenant['name']
        ]);
    }

    /**
     * Show create tenant form
     */
    public function create()
    {
        $access = SuperPanelAccess::getAccess();
        $parentId = (int)($_GET['parent_id'] ?? $access['tenant_id']);

        // Check permission
        $canCreate = SuperPanelAccess::canCreateSubtenantUnder($parentId);
        if (!$canCreate['allowed']) {
            http_response_code(403);
            View::render('errors/403', ['message' => $canCreate['reason']]);
            return;
        }

        $availableParents = TenantVisibilityService::getAvailableParents();
        $selectedParent = Tenant::find($parentId);

        View::render('super-admin/tenants/create', [
            'access' => $access,
            'availableParents' => $availableParents,
            'selectedParent' => $selectedParent,
            'parentId' => $parentId,
            'pageTitle' => 'Create Sub-Tenant'
        ]);
    }

    /**
     * Store new tenant
     */
    public function store()
    {
        Csrf::verifyOrDie();

        $parentId = (int)($_POST['parent_id'] ?? 0);

        if (!$parentId) {
            $this->redirectWithError('/super-admin/tenants/create', 'Parent tenant is required');
            return;
        }

        $data = [
            'name' => $_POST['name'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'domain' => $_POST['domain'] ?? '',
            'tagline' => $_POST['tagline'] ?? '',
            'description' => $_POST['description'] ?? '',
            'allows_subtenants' => isset($_POST['allows_subtenants']),
            'max_depth' => (int)($_POST['max_depth'] ?? 2),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        $result = TenantHierarchyService::createTenant($data, $parentId);

        if ($result['success']) {
            $_SESSION['flash_success'] = "Tenant '{$data['name']}' created successfully";
            header('Location: /super-admin/tenants/' . $result['tenant_id']);
        } else {
            $this->redirectWithError('/super-admin/tenants/create?parent_id=' . $parentId, $result['error']);
        }
    }

    /**
     * Show edit tenant form
     */
    public function edit($id)
    {
        $tenantId = (int)$id;

        if (!SuperPanelAccess::canManageTenant($tenantId)) {
            http_response_code(403);
            View::render('errors/403', ['message' => 'You cannot edit this tenant']);
            return;
        }

        $tenant = TenantVisibilityService::getTenant($tenantId);
        if (!$tenant) {
            http_response_code(404);
            View::render('errors/404', ['message' => 'Tenant not found']);
            return;
        }

        $access = SuperPanelAccess::getAccess();
        $availableParents = TenantVisibilityService::getAvailableParents();

        View::render('super-admin/tenants/edit', [
            'access' => $access,
            'tenant' => $tenant,
            'availableParents' => $availableParents,
            'pageTitle' => 'Edit: ' . $tenant['name']
        ]);
    }

    /**
     * Update tenant
     */
    public function update($id)
    {
        Csrf::verifyOrDie();

        $tenantId = (int)$id;

        // Collect all possible update fields - service will only update what's provided
        $data = [];

        // Basic fields
        $basicFields = ['name', 'slug', 'domain', 'tagline', 'description'];
        foreach ($basicFields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field];
            }
        }

        // Contact fields
        $contactFields = ['contact_email', 'contact_phone', 'address'];
        foreach ($contactFields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field];
            }
        }

        // SEO fields
        $seoFields = ['meta_title', 'meta_description', 'h1_headline', 'hero_intro', 'og_image_url', 'robots_directive'];
        foreach ($seoFields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field];
            }
        }

        // Location fields
        $locationFields = ['location_name', 'country_code', 'service_area', 'latitude', 'longitude'];
        foreach ($locationFields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field];
            }
        }

        // Social media fields
        $socialFields = ['social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin', 'social_youtube'];
        foreach ($socialFields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field];
            }
        }

        // Legal document configuration fields (stored in configuration JSON)
        if (isset($_POST['privacy_text']) || isset($_POST['terms_text'])) {
            // Get current configuration
            $tenant = \Nexus\Models\Tenant::find($tenantId);
            $config = json_decode($tenant['configuration'] ?? '{}', true) ?: [];

            // Update legal text fields
            if (isset($_POST['privacy_text'])) {
                if (empty(trim($_POST['privacy_text']))) {
                    unset($config['privacy_text']);
                } else {
                    $config['privacy_text'] = $_POST['privacy_text'];
                }
            }
            if (isset($_POST['terms_text'])) {
                if (empty(trim($_POST['terms_text']))) {
                    unset($config['terms_text']);
                } else {
                    $config['terms_text'] = $_POST['terms_text'];
                }
            }

            // Store updated configuration
            $data['configuration'] = json_encode($config);
        }

        // Boolean/checkbox fields - only set if explicitly in POST (forms may not submit unchecked checkboxes)
        if (isset($_POST['is_active']) || isset($_POST['name'])) {
            // Only set is_active if this looks like the main details form (has name field)
            // or explicitly has is_active
            $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        }

        if (isset($_POST['allows_subtenants'])) {
            $data['allows_subtenants'] = 1;
        }

        if (isset($_POST['max_depth'])) {
            $data['max_depth'] = (int)$_POST['max_depth'];
        }

        // Handle platform module features (stored in features JSON column)
        if (isset($_POST['update_modules'])) {
            $moduleKeys = ['listings', 'groups', 'wallet', 'volunteering', 'events', 'resources', 'polls', 'goals', 'blog', 'help_center'];
            $features = [];
            foreach ($moduleKeys as $key) {
                $features[$key] = isset($_POST['feat_' . $key]);
            }
            $data['features'] = json_encode($features);
        }

        $result = TenantHierarchyService::updateTenant($tenantId, $data);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Tenant updated successfully';
            header('Location: /super-admin/tenants/' . $tenantId . '/edit');
        } else {
            $this->redirectWithError('/super-admin/tenants/' . $tenantId . '/edit', $result['error']);
        }
        exit;
    }

    /**
     * Delete/deactivate tenant
     */
    public function delete($id)
    {
        Csrf::verifyOrDie();

        $tenantId = (int)$id;
        $hardDelete = isset($_POST['hard_delete']);

        $result = TenantHierarchyService::deleteTenant($tenantId, $hardDelete);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Tenant deactivated successfully';
            header('Location: /super-admin/tenants');
        } else {
            $this->redirectWithError('/super-admin/tenants/' . $tenantId, $result['error']);
        }
    }

    /**
     * Reactivate an inactive tenant
     */
    public function reactivate($id)
    {
        Csrf::verifyOrDie();

        $tenantId = (int)$id;

        if (!SuperPanelAccess::canManageTenant($tenantId)) {
            http_response_code(403);
            $this->redirectWithError('/super-admin/tenants/' . $tenantId, 'You cannot manage this tenant');
            return;
        }

        $result = TenantHierarchyService::updateTenant($tenantId, ['is_active' => 1]);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Tenant reactivated successfully - users can now access it';
            header('Location: /super-admin/tenants/' . $tenantId);
        } else {
            $this->redirectWithError('/super-admin/tenants/' . $tenantId, $result['error']);
        }
        exit;
    }

    /**
     * Toggle sub-tenant capability
     */
    public function toggleHub($id)
    {
        Csrf::verifyOrDie();

        $tenantId = (int)$id;
        $enable = isset($_POST['enable']) && $_POST['enable'] === '1';

        $result = TenantHierarchyService::toggleSubtenantCapability($tenantId, $enable);

        if ($result['success']) {
            $_SESSION['flash_success'] = $enable
                ? 'Sub-tenant capability enabled - this tenant can now create sub-tenants'
                : 'Sub-tenant capability disabled';
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }

        header('Location: /super-admin/tenants/' . $tenantId);
    }

    /**
     * Move tenant to a new parent (re-parent)
     */
    public function move($id)
    {
        Csrf::verifyOrDie();

        $tenantId = (int)$id;
        $newParentId = (int)($_POST['new_parent_id'] ?? 0);

        if (!$newParentId) {
            $this->redirectWithError('/super-admin/tenants/' . $tenantId . '/edit', 'Please select a new parent tenant');
            return;
        }

        $result = TenantHierarchyService::moveTenant($tenantId, $newParentId);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Tenant moved successfully to new parent';
            header('Location: /super-admin/tenants/' . $tenantId);
        } else {
            $this->redirectWithError('/super-admin/tenants/' . $tenantId . '/edit', $result['error']);
        }
    }

    /**
     * API: Get tenant hierarchy as JSON
     */
    public function apiHierarchy()
    {
        header('Content-Type: application/json');

        $tree = TenantVisibilityService::getHierarchyTree();

        echo json_encode([
            'success' => true,
            'hierarchy' => $tree
        ]);
    }

    /**
     * API: Get tenants list as JSON
     */
    public function apiList()
    {
        header('Content-Type: application/json');

        $tenants = TenantVisibilityService::getTenantList();

        echo json_encode([
            'success' => true,
            'tenants' => $tenants
        ]);
    }

    /**
     * Helper: Redirect with error message
     */
    private function redirectWithError(string $url, string $error): void
    {
        $_SESSION['flash_error'] = $error;
        header('Location: ' . $url);
        exit;
    }
}
