<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Middleware\SuperPanelAccess;
use Nexus\Services\SuperAdminAuditService;

/**
 * TenantHierarchyService
 *
 * Handles tenant CRUD operations with hierarchy management.
 * Creates, updates, and deletes tenants while maintaining path integrity.
 */
class TenantHierarchyService
{
    /**
     * Create a new tenant
     *
     * @param array $data Tenant data
     * @param int $parentId Parent tenant ID
     * @return array ['success' => bool, 'tenant_id' => int|null, 'error' => string|null]
     */
    public static function createTenant(array $data, int $parentId): array
    {
        // Check permission
        $canCreate = SuperPanelAccess::canCreateSubtenantUnder($parentId);
        if (!$canCreate['allowed']) {
            return ['success' => false, 'tenant_id' => null, 'error' => $canCreate['reason']];
        }

        // Validate required fields
        $name = trim($data['name'] ?? '');
        $slug = trim($data['slug'] ?? '');

        if (empty($name)) {
            return ['success' => false, 'tenant_id' => null, 'error' => 'Tenant name is required'];
        }

        if (empty($slug)) {
            $slug = self::generateSlug($name);
        }

        $domain = trim($data['domain'] ?? '') ?: null;

        // Determine sub-tenant settings
        $allowsSubtenants = (bool)($data['allows_subtenants'] ?? false);
        $maxDepth = $allowsSubtenants ? max(0, (int)($data['max_depth'] ?? 2)) : 0;

        try {
            Database::beginTransaction();

            // Uniqueness checks inside transaction to prevent TOCTOU race
            $existing = Database::query(
                "SELECT id FROM tenants WHERE slug = ?",
                [$slug]
            )->fetch();

            if ($existing) {
                Database::rollback();
                return ['success' => false, 'tenant_id' => null, 'error' => 'Slug already exists'];
            }

            if ($domain) {
                $existingDomain = Database::query(
                    "SELECT id FROM tenants WHERE domain = ?",
                    [$domain]
                )->fetch();

                if ($existingDomain) {
                    Database::rollback();
                    return ['success' => false, 'tenant_id' => null, 'error' => 'Domain already in use'];
                }
            }

            // Re-validate parent inside transaction
            $parent = Database::query(
                "SELECT path, depth FROM tenants WHERE id = ?",
                [$parentId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$parent) {
                Database::rollback();
                return ['success' => false, 'tenant_id' => null, 'error' => 'Parent tenant not found'];
            }

            $newDepth = (int)$parent['depth'] + 1;

            // Encode features JSON if provided
            $featuresJson = null;
            if (!empty($data['features'])) {
                $featuresJson = is_string($data['features']) ? $data['features'] : json_encode($data['features']);
            }

            // Encode configuration JSON if provided (languages, etc.)
            $configurationJson = null;
            if (!empty($data['configuration'])) {
                $configurationJson = is_string($data['configuration']) ? $data['configuration'] : json_encode($data['configuration']);
            }

            // Insert tenant with all fields
            Database::query("
                INSERT INTO tenants (
                    name, slug, domain, tagline, description,
                    parent_id, depth, allows_subtenants, max_depth,
                    is_active, features, configuration, contact_email, contact_phone, address,
                    meta_title, meta_description, h1_headline, hero_intro, og_image_url, robots_directive,
                    location_name, country_code, service_area, latitude, longitude,
                    social_facebook, social_twitter, social_instagram, social_linkedin, social_youtube,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $name,
                $slug,
                $domain,
                trim($data['tagline'] ?? '') ?: null,
                trim($data['description'] ?? '') ?: null,
                $parentId,
                $newDepth,
                $allowsSubtenants ? 1 : 0,
                $maxDepth,
                isset($data['is_active']) ? (int)$data['is_active'] : 1,
                $featuresJson,
                $configurationJson,
                trim($data['contact_email'] ?? '') ?: null,
                trim($data['contact_phone'] ?? '') ?: null,
                trim($data['address'] ?? '') ?: null,
                // SEO
                trim($data['meta_title'] ?? '') ?: null,
                trim($data['meta_description'] ?? '') ?: null,
                trim($data['h1_headline'] ?? '') ?: null,
                trim($data['hero_intro'] ?? '') ?: null,
                trim($data['og_image_url'] ?? '') ?: null,
                trim($data['robots_directive'] ?? '') ?: null,
                // Location
                trim($data['location_name'] ?? '') ?: null,
                trim($data['country_code'] ?? '') ?: null,
                trim($data['service_area'] ?? '') ?: null,
                ($data['latitude'] ?? '') !== '' ? (float)$data['latitude'] : null,
                ($data['longitude'] ?? '') !== '' ? (float)$data['longitude'] : null,
                // Social
                trim($data['social_facebook'] ?? '') ?: null,
                trim($data['social_twitter'] ?? '') ?: null,
                trim($data['social_instagram'] ?? '') ?: null,
                trim($data['social_linkedin'] ?? '') ?: null,
                trim($data['social_youtube'] ?? '') ?: null,
            ]);

            $tenantId = Database::lastInsertId();

            // Set the path (parent_path + tenant_id + /)
            $newPath = $parent['path'] . $tenantId . '/';
            Database::query(
                "UPDATE tenants SET path = ? WHERE id = ?",
                [$newPath, $tenantId]
            );

            // Seed all default data for the new tenant
            // Core seeders — tables guaranteed to exist but wrapped for resilience
            try {
                \Nexus\Models\Attribute::seedDefaults($tenantId);
            } catch (\Exception $attrErr) {
                error_log("TenantHierarchyService: attribute seeding failed for tenant {$tenantId}: " . $attrErr->getMessage());
            }
            try {
                \Nexus\Models\Category::seedDefaults($tenantId);
            } catch (\Exception $catErr) {
                error_log("TenantHierarchyService: category seeding failed for tenant {$tenantId}: " . $catErr->getMessage());
            }
            try {
                TenantSettingsService::seedDefaults((int)$tenantId);
            } catch (\Exception $settErr) {
                error_log("TenantHierarchyService: settings seeding failed for tenant {$tenantId}: " . $settErr->getMessage());
            }
            try {
                \Nexus\Models\Menu::seedDefaults((int)$tenantId);
            } catch (\Exception $menuErr) {
                error_log("TenantHierarchyService: menu seeding failed for tenant {$tenantId}: " . $menuErr->getMessage());
            }
            // Skills seeding is non-critical — table may not exist on fresh installs
            try {
                SkillTaxonomyService::seedDefaults((int)$tenantId);
            } catch (\Exception $skillErr) {
                error_log("TenantHierarchyService: skill seeding skipped for tenant {$tenantId}: " . $skillErr->getMessage());
            }

            // Pages seeding is non-critical — table may not exist on fresh installs
            try {
                \Nexus\Models\Page::seedDefaults((int)$tenantId);
            } catch (\Exception $pageErr) {
                error_log("TenantHierarchyService: page seeding skipped for tenant {$tenantId}: " . $pageErr->getMessage());
            }

            // Seed default features — enable ALL features for new tenants
            $allFeaturesEnabled = array_map(fn() => true, TenantFeatureConfig::FEATURE_DEFAULTS);
            if (!empty($data['features'])) {
                // Merge user-provided features over all-enabled defaults
                $allFeaturesEnabled = array_merge($allFeaturesEnabled, $data['features']);
            }
            Database::query(
                "UPDATE tenants SET features = ? WHERE id = ?",
                [json_encode($allFeaturesEnabled), $tenantId]
            );

            // Seed federation tenant features so federation API recognises this tenant
            try {
                FederationFeatureService::enableTenantFeature(
                    FederationFeatureService::TENANT_FEDERATION_ENABLED,
                    (int)$tenantId
                );
                FederationFeatureService::enableTenantFeature(
                    FederationFeatureService::TENANT_APPEAR_IN_DIRECTORY,
                    (int)$tenantId
                );
            } catch (\Exception $fedErr) {
                error_log("TenantHierarchyService: federation seeding skipped for tenant {$tenantId}: " . $fedErr->getMessage());
            }

            Database::commit();

            // Clear tenant list cache so the new tenant appears immediately
            try {
                RedisCache::delete('tenants_list_public');
                RedisCache::delete('tenants_list_public_all');
            } catch (\Exception $cacheErr) {
                error_log("TenantHierarchyService: cache clear failed for tenant {$tenantId}: " . $cacheErr->getMessage());
            }

            // Audit log (wrapped in try-catch so audit failure doesn't mask success)
            try {
                SuperAdminAuditService::log(
                    'tenant_created',
                    'tenant',
                    (int)$tenantId,
                    $name,
                    null,
                    ['parent_id' => $parentId, 'slug' => $slug, 'path' => $newPath],
                    "Created tenant '{$name}' under parent ID {$parentId}"
                );
            } catch (\Exception $auditErr) {
                error_log("TenantHierarchyService: audit log failed for tenant {$tenantId}: " . $auditErr->getMessage());
            }

            return [
                'success' => true,
                'tenant_id' => (int)$tenantId,
                'error' => null,
                'path' => $newPath
            ];

        } catch (\Exception $e) {
            Database::rollback();
            error_log("TenantHierarchyService::createTenant error: " . $e->getMessage());
            return ['success' => false, 'tenant_id' => null, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Update a tenant
     *
     * @param int $tenantId Tenant ID to update
     * @param array $data Updated data
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function updateTenant(int $tenantId, array $data): array
    {
        // Check permission
        if (!SuperPanelAccess::canManageTenant($tenantId)) {
            return ['success' => false, 'error' => 'You cannot manage this tenant'];
        }

        // Get current tenant
        $tenant = Database::query(
            "SELECT * FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$tenant) {
            return ['success' => false, 'error' => 'Tenant not found'];
        }

        try {
            Database::beginTransaction();

        // Build update fields
        $updates = [];
        $params = [];

        // Name
        if (isset($data['name']) && trim($data['name']) !== '') {
            $updates[] = "name = ?";
            $params[] = trim($data['name']);
        }

        // Slug (check uniqueness inside transaction to prevent TOCTOU race)
        if (isset($data['slug']) && trim($data['slug']) !== '' && $data['slug'] !== $tenant['slug']) {
            $existing = Database::query(
                "SELECT id FROM tenants WHERE slug = ? AND id != ?",
                [trim($data['slug']), $tenantId]
            )->fetch();

            if ($existing) {
                Database::rollback();
                return ['success' => false, 'error' => 'Slug already exists'];
            }

            $updates[] = "slug = ?";
            $params[] = trim($data['slug']);
        }

        // Domain (check uniqueness inside transaction)
        if (array_key_exists('domain', $data)) {
            $domain = trim($data['domain']) ?: null;
            if ($domain && $domain !== $tenant['domain']) {
                $existing = Database::query(
                    "SELECT id FROM tenants WHERE domain = ? AND id != ?",
                    [$domain, $tenantId]
                )->fetch();

                if ($existing) {
                    Database::rollback();
                    return ['success' => false, 'error' => 'Domain already in use'];
                }
            }
            $updates[] = "domain = ?";
            $params[] = $domain;
        }

        // Simple text fields
        foreach (['tagline', 'description', 'contact_email', 'contact_phone', 'address'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = trim($data[$field]) ?: null;
            }
        }

        // SEO fields
        foreach (['meta_title', 'meta_description', 'h1_headline', 'hero_intro', 'og_image_url', 'robots_directive'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = trim($data[$field]) ?: null;
            }
        }

        // Location fields
        foreach (['location_name', 'country_code', 'service_area'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = trim($data[$field]) ?: null;
            }
        }

        // Latitude/Longitude (decimal)
        if (array_key_exists('latitude', $data)) {
            $updates[] = "latitude = ?";
            $params[] = $data['latitude'] !== '' ? (float)$data['latitude'] : null;
        }
        if (array_key_exists('longitude', $data)) {
            $updates[] = "longitude = ?";
            $params[] = $data['longitude'] !== '' ? (float)$data['longitude'] : null;
        }

        // Social media fields
        foreach (['social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin', 'social_youtube'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = trim($data[$field]) ?: null;
            }
        }

        // Boolean/integer fields
        if (isset($data['is_active'])) {
            // Prevent deactivating Master tenant
            if ($tenantId === 1 && !$data['is_active']) {
                Database::rollback();
                return ['success' => false, 'error' => 'Cannot deactivate Master tenant'];
            }
            $updates[] = "is_active = ?";
            $params[] = (int)$data['is_active'];
        }

        if (isset($data['allows_subtenants'])) {
            $updates[] = "allows_subtenants = ?";
            $params[] = (int)$data['allows_subtenants'];
        }

        if (isset($data['max_depth'])) {
            $updates[] = "max_depth = ?";
            $params[] = max(0, (int)$data['max_depth']);
        }

        // Configuration JSON field
        if (array_key_exists('configuration', $data)) {
            $updates[] = "configuration = ?";
            $params[] = is_string($data['configuration']) ? $data['configuration'] : json_encode($data['configuration']);
        }

        // Features JSON field (platform modules)
        if (array_key_exists('features', $data)) {
            $updates[] = "features = ?";
            $params[] = is_string($data['features']) ? $data['features'] : json_encode($data['features']);
        }

        if (empty($updates)) {
            Database::commit();
            return ['success' => true, 'error' => null]; // Nothing to update
        }

        $params[] = $tenantId;

        Database::query(
            "UPDATE tenants SET " . implode(', ', $updates) . " WHERE id = ?",
            $params
        );

        Database::commit();

        // Audit log (post-commit, non-critical)
        try {
            SuperAdminAuditService::log(
                'tenant_updated',
                'tenant',
                $tenantId,
                $tenant['name'],
                $tenant,
                $data,
                "Updated tenant '{$tenant['name']}'"
            );
        } catch (\Exception $auditErr) {
            error_log("TenantHierarchyService: audit log failed for tenant {$tenantId}: " . $auditErr->getMessage());
        }

        return ['success' => true, 'error' => null];

        } catch (\Exception $e) {
            Database::rollback();
            error_log("TenantHierarchyService::updateTenant error for tenant {$tenantId}: " . $e->getMessage());
            $safeMessage = (getenv('APP_ENV') === 'development' || getenv('APP_ENV') === 'local')
                ? 'Database error: ' . $e->getMessage()
                : 'Database error updating tenant';
            return ['success' => false, 'error' => $safeMessage];
        }
    }

    /**
     * Delete a tenant (soft delete - deactivate)
     *
     * @param int $tenantId Tenant ID to delete
     * @param bool $hardDelete Permanently delete (dangerous!)
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function deleteTenant(int $tenantId, bool $hardDelete = false): array
    {
        // Check permission
        if (!SuperPanelAccess::canManageTenant($tenantId)) {
            return ['success' => false, 'error' => 'You cannot manage this tenant'];
        }

        // Cannot delete Master tenant
        if ($tenantId === 1) {
            return ['success' => false, 'error' => 'Cannot delete Master tenant'];
        }

        // Check for children
        $childCount = Database::query(
            "SELECT COUNT(*) FROM tenants WHERE parent_id = ?",
            [$tenantId]
        )->fetchColumn();

        if ($childCount > 0) {
            return ['success' => false, 'error' => "Cannot delete tenant with {$childCount} sub-tenant(s). Delete children first."];
        }

        // Hard delete is not yet implemented — reject explicitly rather than silently soft-deleting
        if ($hardDelete) {
            return ['success' => false, 'error' => 'Hard delete is not yet implemented. Use soft delete (deactivate) instead.'];
        }

        try {
            // Soft delete (deactivate)
            Database::query("UPDATE tenants SET is_active = 0 WHERE id = ?", [$tenantId]);

            // Audit log
            $tenant = Database::query("SELECT name FROM tenants WHERE id = ?", [$tenantId])->fetch(\PDO::FETCH_ASSOC);
            SuperAdminAuditService::log(
                'tenant_deleted',
                'tenant',
                $tenantId,
                $tenant['name'] ?? 'Unknown',
                null,
                null,
                "Deactivated tenant '{$tenant['name']}'"
            );

            return ['success' => true, 'error' => null];

        } catch (\Exception $e) {
            error_log("TenantHierarchyService::deleteTenant error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }

    /**
     * Move a tenant to a new parent
     *
     * @param int $tenantId Tenant to move
     * @param int $newParentId New parent tenant ID
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function moveTenant(int $tenantId, int $newParentId): array
    {
        // Check permissions
        if (!SuperPanelAccess::canManageTenant($tenantId)) {
            return ['success' => false, 'error' => 'You cannot manage this tenant'];
        }

        $canCreate = SuperPanelAccess::canCreateSubtenantUnder($newParentId);
        if (!$canCreate['allowed']) {
            return ['success' => false, 'error' => $canCreate['reason']];
        }

        // Cannot move to self or descendant
        $tenant = Database::query("SELECT path, depth, is_active FROM tenants WHERE id = ?", [$tenantId])->fetch(\PDO::FETCH_ASSOC);
        $newParent = Database::query("SELECT path, depth FROM tenants WHERE id = ?", [$newParentId])->fetch(\PDO::FETCH_ASSOC);

        if (!$tenant || !$newParent) {
            return ['success' => false, 'error' => 'Tenant not found'];
        }

        if (!(int)$tenant['is_active']) {
            return ['success' => false, 'error' => 'Cannot move a deactivated tenant'];
        }

        // Check if new parent is a descendant of tenant (would create cycle)
        if (str_starts_with($newParent['path'], $tenant['path'])) {
            return ['success' => false, 'error' => 'Cannot move tenant under its own descendant'];
        }

        try {
            Database::beginTransaction();

            // Calculate new path and depth
            $newPath = $newParent['path'] . $tenantId . '/';
            $newDepth = (int)$newParent['depth'] + 1;
            $oldPath = $tenant['path'];

            // Update the tenant
            Database::query("
                UPDATE tenants SET parent_id = ?, path = ?, depth = ? WHERE id = ?
            ", [$newParentId, $newPath, $newDepth, $tenantId]);

            // Update all descendants' paths
            $descendants = Database::query(
                "SELECT id, path, depth FROM tenants WHERE path LIKE ? AND id != ?",
                [$oldPath . '%', $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($descendants as $desc) {
                // Replace old path prefix with new path prefix
                $descNewPath = $newPath . substr($desc['path'], strlen($oldPath));
                $depthDiff = $newDepth - (int)$tenant['depth'];
                $descNewDepth = (int)$desc['depth'] + $depthDiff;

                Database::query(
                    "UPDATE tenants SET path = ?, depth = ? WHERE id = ?",
                    [$descNewPath, $descNewDepth, $desc['id']]
                );
            }

            Database::commit();

            // Audit log
            $tenantName = Database::query("SELECT name FROM tenants WHERE id = ?", [$tenantId])->fetchColumn();
            $newParentName = Database::query("SELECT name FROM tenants WHERE id = ?", [$newParentId])->fetchColumn();
            SuperAdminAuditService::log(
                'tenant_moved',
                'tenant',
                $tenantId,
                $tenantName,
                ['path' => $oldPath],
                ['path' => $newPath, 'parent_id' => $newParentId],
                "Moved tenant '{$tenantName}' to new parent '{$newParentName}'"
            );

            return ['success' => true, 'error' => null];

        } catch (\Exception $e) {
            Database::rollback();
            error_log("TenantHierarchyService::moveTenant error for tenant {$tenantId}: " . $e->getMessage());
            $safeMessage = (getenv('APP_ENV') === 'development' || getenv('APP_ENV') === 'local')
                ? 'Database error: ' . $e->getMessage()
                : 'Database error moving tenant';
            return ['success' => false, 'error' => $safeMessage];
        }
    }

    /**
     * Toggle allows_subtenants flag for a tenant
     */
    public static function toggleSubtenantCapability(int $tenantId, bool $enable): array
    {
        if (!SuperPanelAccess::canManageTenant($tenantId)) {
            return ['success' => false, 'error' => 'You cannot manage this tenant'];
        }

        try {
            Database::query(
                "UPDATE tenants SET allows_subtenants = ?, max_depth = ? WHERE id = ?",
                [$enable ? 1 : 0, $enable ? 2 : 0, $tenantId]
            );

            // Audit log
            $tenantName = Database::query("SELECT name FROM tenants WHERE id = ?", [$tenantId])->fetchColumn();
            SuperAdminAuditService::log(
                'hub_toggled',
                'tenant',
                $tenantId,
                $tenantName,
                ['allows_subtenants' => !$enable],
                ['allows_subtenants' => $enable],
                $enable ? "Enabled Hub capability for '{$tenantName}'" : "Disabled Hub capability for '{$tenantName}'"
            );

            return ['success' => true, 'error' => null];

        } catch (\Exception $e) {
            error_log("TenantHierarchyService::toggleSubtenantCapability error for tenant {$tenantId}: " . $e->getMessage());
            $safeMessage = (getenv('APP_ENV') === 'development' || getenv('APP_ENV') === 'local')
                ? 'Database error: ' . $e->getMessage()
                : 'Database error toggling hub status';
            return ['success' => false, 'error' => $safeMessage];
        }
    }

    /**
     * Assign a user as tenant super admin
     */
    public static function assignTenantSuperAdmin(int $userId, int $tenantId): array
    {
        if (!SuperPanelAccess::canManageTenant($tenantId)) {
            return ['success' => false, 'error' => 'You cannot manage this tenant'];
        }

        // Verify user belongs to this tenant
        $user = Database::query(
            "SELECT id, tenant_id, first_name, last_name, email FROM users WHERE id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        if ((int)$user['tenant_id'] !== $tenantId) {
            return ['success' => false, 'error' => 'User does not belong to this tenant'];
        }

        try {
            Database::query(
                "UPDATE users SET is_tenant_super_admin = 1, role = 'tenant_admin' WHERE id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );

            // Audit log
            $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['email'];
            SuperAdminAuditService::log(
                'super_admin_granted',
                'user',
                $userId,
                $userName,
                ['is_tenant_super_admin' => false],
                ['is_tenant_super_admin' => true, 'tenant_id' => $tenantId],
                "Granted Super Admin privileges to '{$userName}'"
            );

            return ['success' => true, 'error' => null];

        } catch (\Exception $e) {
            error_log("TenantHierarchyService::assignTenantSuperAdmin error for user {$userId}: " . $e->getMessage());
            $safeMessage = (getenv('APP_ENV') === 'development' || getenv('APP_ENV') === 'local')
                ? 'Database error: ' . $e->getMessage()
                : 'Database error granting super admin';
            return ['success' => false, 'error' => $safeMessage];
        }
    }

    /**
     * Revoke tenant super admin from a user
     */
    public static function revokeTenantSuperAdmin(int $userId): array
    {
        $user = Database::query(
            "SELECT id, tenant_id, first_name, last_name, email FROM users WHERE id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        if (!SuperPanelAccess::canManageTenant((int)$user['tenant_id'])) {
            return ['success' => false, 'error' => 'You cannot manage this tenant'];
        }

        try {
            Database::query(
                "UPDATE users SET is_tenant_super_admin = 0 WHERE id = ? AND tenant_id = ?",
                [$userId, (int)$user['tenant_id']]
            );

            // Audit log
            $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['email'];
            SuperAdminAuditService::log(
                'super_admin_revoked',
                'user',
                $userId,
                $userName,
                ['is_tenant_super_admin' => true],
                ['is_tenant_super_admin' => false],
                "Revoked Super Admin privileges from '{$userName}'"
            );

            return ['success' => true, 'error' => null];

        } catch (\Exception $e) {
            error_log("TenantHierarchyService::revokeTenantSuperAdmin error for user {$userId}: " . $e->getMessage());
            $safeMessage = (getenv('APP_ENV') === 'development' || getenv('APP_ENV') === 'local')
                ? 'Database error: ' . $e->getMessage()
                : 'Database error revoking super admin';
            return ['success' => false, 'error' => $safeMessage];
        }
    }

    /**
     * Generate a URL-safe slug from a name
     */
    private static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Fallback if name sanitizes to empty (e.g. "!!!" or non-Latin chars)
        if ($slug === '') {
            $slug = 'tenant-' . time();
        }

        // Ensure uniqueness (capped at 100 attempts to prevent infinite loop)
        $baseSlug = $slug;
        $counter = 1;

        while (Database::query("SELECT id FROM tenants WHERE slug = ?", [$slug])->fetch()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            if ($counter > 100) {
                $slug = $baseSlug . '-' . bin2hex(random_bytes(4));
                break;
            }
        }

        return $slug;
    }
}
