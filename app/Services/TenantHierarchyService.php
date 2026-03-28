<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TenantHierarchyService — Native Laravel implementation.
 *
 * Manages tenant CRUD operations, hierarchy movement, and super-admin
 * assignment/revocation. All mutations are audited via SuperAdminAuditService.
 */
class TenantHierarchyService
{
    public function __construct()
    {
    }

    /**
     * Create a new tenant under the given parent.
     *
     * @return array{success: bool, tenant_id?: int, error?: string}
     */
    public static function createTenant(array $data, int $parentId): array
    {
        try {
            // Validate parent exists
            $parent = DB::table('tenants')->where('id', $parentId)->first();
            if (!$parent) {
                return ['success' => false, 'error' => 'Parent tenant not found'];
            }

            // Check parent allows sub-tenants
            if (!$parent->allows_subtenants && $parentId !== 1) {
                return ['success' => false, 'error' => 'Parent tenant does not allow sub-tenants'];
            }

            // Check max depth
            $newDepth = ($parent->depth ?? 0) + 1;
            if ($parent->max_depth > 0 && $newDepth > $parent->max_depth) {
                return ['success' => false, 'error' => 'Maximum hierarchy depth exceeded'];
            }

            $name = trim($data['name'] ?? '');
            if (empty($name)) {
                return ['success' => false, 'error' => 'Tenant name is required'];
            }

            // Generate slug if not provided
            $slug = trim($data['slug'] ?? '');
            if (empty($slug)) {
                $slug = self::generateSlug($name);
            }

            // Check slug uniqueness
            $existingSlug = DB::table('tenants')->where('slug', $slug)->exists();
            if ($existingSlug) {
                return ['success' => false, 'error' => "Slug '{$slug}' is already in use"];
            }

            // Check domain uniqueness if provided
            $domain = trim($data['domain'] ?? '');
            if ($domain !== '') {
                $existingDomain = DB::table('tenants')->where('domain', $domain)->exists();
                if ($existingDomain) {
                    return ['success' => false, 'error' => "Domain '{$domain}' is already in use"];
                }
            }

            // Handle features — store as JSON
            // If no features provided, use sensible defaults (conservative — most optional features off)
            $features = null;
            if (isset($data['features'])) {
                $features = is_string($data['features'])
                    ? $data['features']
                    : json_encode($data['features']);
            } else {
                // Default: core features on, advanced features off for new tenants
                $features = json_encode([
                    'events' => true,
                    'groups' => true,
                    'gamification' => false,
                    'goals' => false,
                    'blog' => true,
                    'resources' => false,
                    'volunteering' => false,
                    'exchange_workflow' => false,
                    'organisations' => false,
                    'federation' => false,
                    'connections' => true,
                    'reviews' => true,
                    'polls' => false,
                    'job_vacancies' => false,
                    'ideation_challenges' => false,
                    'direct_messaging' => true,
                    'group_exchanges' => false,
                    'search' => true,
                    'ai_chat' => false,
                ]);
            }

            // Handle configuration — store as JSON
            $configuration = null;
            if (isset($data['configuration'])) {
                $configuration = is_string($data['configuration'])
                    ? $data['configuration']
                    : json_encode($data['configuration']);
            }

            // Wrap entire creation in a transaction to prevent half-initialized tenants
            $tenantId = DB::transaction(function () use (
                $name, $slug, $domain, $data, $parentId, $newDepth, $features, $configuration, $parent
            ) {
                $tenantId = DB::table('tenants')->insertGetId([
                    'name'               => $name,
                    'slug'               => $slug,
                    'domain'             => $domain ?: null,
                    'tagline'            => $data['tagline'] ?? null,
                    'description'        => $data['description'] ?? null,
                    'parent_id'          => $parentId,
                    'depth'              => $newDepth,
                    'allows_subtenants'  => !empty($data['allows_subtenants']) ? 1 : 0,
                    'max_depth'          => (int) ($data['max_depth'] ?? 2),
                    'is_active'          => isset($data['is_active']) ? (int) $data['is_active'] : 1,
                    'features'           => $features,
                    'configuration'      => $configuration,
                    'contact_email'      => $data['contact_email'] ?? null,
                    'contact_phone'      => $data['contact_phone'] ?? null,
                    'address'            => $data['address'] ?? null,
                    // SEO fields
                    'meta_title'         => $data['meta_title'] ?? null,
                    'meta_description'   => $data['meta_description'] ?? null,
                    'h1_headline'        => $data['h1_headline'] ?? null,
                    'hero_intro'         => $data['hero_intro'] ?? null,
                    'og_image_url'       => $data['og_image_url'] ?? null,
                    'robots_directive'   => $data['robots_directive'] ?? 'index, follow',
                    // Location fields
                    'location_name'      => $data['location_name'] ?? null,
                    'country_code'       => $data['country_code'] ?? null,
                    'service_area'       => $data['service_area'] ?? 'national',
                    'latitude'           => $data['latitude'] ?: null,
                    'longitude'          => $data['longitude'] ?: null,
                    // Social media
                    'social_facebook'    => $data['social_facebook'] ?? null,
                    'social_twitter'     => $data['social_twitter'] ?? null,
                    'social_instagram'   => $data['social_instagram'] ?? null,
                    'social_linkedin'    => $data['social_linkedin'] ?? null,
                    'social_youtube'     => $data['social_youtube'] ?? null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                // Set materialized path: parent path + own ID
                $parentPath = $parent->path ?? ('/' . $parentId . '/');
                $path = $parentPath . $tenantId . '/';
                DB::table('tenants')->where('id', $tenantId)->update(['path' => $path]);

                // Seed required default data for the new tenant
                self::seedTenantDefaults($tenantId);

                return $tenantId;
            });

            // Audit (outside transaction — non-critical, should not block creation)
            SuperAdminAuditService::log(
                'tenant_created',
                'tenant',
                $tenantId,
                $name,
                null,
                ['parent_id' => $parentId, 'slug' => $slug],
                "Created tenant '{$name}' under parent ID {$parentId}"
            );

            return ['success' => true, 'tenant_id' => $tenantId];
        } catch (\Throwable $e) {
            Log::error('TenantHierarchyService::createTenant failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to create tenant: ' . $e->getMessage()];
        }
    }

    /**
     * Update an existing tenant.
     *
     * @return array{success: bool, error?: string}
     */
    public static function updateTenant(int $tenantId, array $data): array
    {
        try {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if (!$tenant) {
                return ['success' => false, 'error' => 'Tenant not found'];
            }

            // Build update array from allowed fields
            $allowed = [
                'name', 'slug', 'domain', 'tagline', 'description',
                'allows_subtenants', 'max_depth', 'is_active', 'features', 'configuration',
                'contact_email', 'contact_phone', 'address',
                'meta_title', 'meta_description', 'h1_headline', 'hero_intro',
                'og_image_url', 'robots_directive',
                'location_name', 'country_code', 'service_area', 'latitude', 'longitude',
                'social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin', 'social_youtube',
            ];

            $update = [];
            $oldValues = [];

            foreach ($allowed as $field) {
                if (!array_key_exists($field, $data)) {
                    continue;
                }

                $value = $data[$field];

                // Handle JSON fields
                if (in_array($field, ['features', 'configuration'], true) && is_array($value)) {
                    $value = json_encode($value);
                }

                // Handle booleans/integers
                if (in_array($field, ['allows_subtenants', 'is_active'], true)) {
                    $value = (int) (bool) $value;
                }
                if ($field === 'max_depth') {
                    $value = (int) $value;
                }

                // Check slug uniqueness if being changed
                if ($field === 'slug' && $value !== ($tenant->slug ?? '')) {
                    $existingSlug = DB::table('tenants')
                        ->where('slug', $value)
                        ->where('id', '!=', $tenantId)
                        ->exists();
                    if ($existingSlug) {
                        return ['success' => false, 'error' => "Slug '{$value}' is already in use"];
                    }
                }

                // Check domain uniqueness if being changed
                if ($field === 'domain' && $value !== ($tenant->domain ?? '') && !empty($value)) {
                    $existingDomain = DB::table('tenants')
                        ->where('domain', $value)
                        ->where('id', '!=', $tenantId)
                        ->exists();
                    if ($existingDomain) {
                        return ['success' => false, 'error' => "Domain '{$value}' is already in use"];
                    }
                }

                $oldValues[$field] = $tenant->$field ?? null;
                $update[$field] = $value;
            }

            if (empty($update)) {
                return ['success' => false, 'error' => 'No valid fields to update'];
            }

            $update['updated_at'] = now();

            DB::table('tenants')->where('id', $tenantId)->update($update);

            // Audit
            SuperAdminAuditService::log(
                'tenant_updated',
                'tenant',
                $tenantId,
                $tenant->name,
                $oldValues,
                $update,
                "Updated tenant '{$tenant->name}' (" . implode(', ', array_keys($update)) . ")"
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('TenantHierarchyService::updateTenant failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to update tenant: ' . $e->getMessage()];
        }
    }

    /**
     * Delete (soft or hard) a tenant.
     *
     * Soft delete sets is_active = 0. Hard delete removes the row entirely.
     * Cannot delete the Master tenant (ID 1) or tenants with active children.
     *
     * @return array{success: bool, error?: string}
     */
    public static function deleteTenant(int $tenantId, bool $hardDelete = false): array
    {
        try {
            if ($tenantId === 1) {
                return ['success' => false, 'error' => 'Cannot delete the Master tenant'];
            }

            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if (!$tenant) {
                return ['success' => false, 'error' => 'Tenant not found'];
            }

            // Check for active children
            $activeChildren = DB::table('tenants')
                ->where('parent_id', $tenantId)
                ->where('is_active', 1)
                ->count();

            if ($activeChildren > 0) {
                return ['success' => false, 'error' => 'Cannot delete a tenant with active sub-tenants. Deactivate or move them first.'];
            }

            if ($hardDelete) {
                // Hard delete is wrapped in a transaction to prevent partial cleanup.
                // WARNING: This only moves users and child tenants. Other tenant-scoped
                // data (listings, transactions, messages, events, groups, etc.) will be
                // orphaned. Hard delete should only be used for empty/test tenants.
                DB::transaction(function () use ($tenantId, $tenant) {
                    $parentId = $tenant->parent_id ?? 1;

                    // Move users to parent tenant before deleting
                    DB::table('users')->where('tenant_id', $tenantId)->update(['tenant_id' => $parentId]);

                    // Reassign orphaned child tenants to parent
                    DB::table('tenants')->where('parent_id', $tenantId)->update([
                        'parent_id' => $parentId,
                    ]);

                    // Clean up tenant-specific seed data
                    DB::table('tenant_settings')->where('tenant_id', $tenantId)->delete();
                    DB::table('categories')->where('tenant_id', $tenantId)->delete();
                    DB::table('federation_tenant_features')->where('tenant_id', $tenantId)->delete();

                    DB::table('tenants')->where('id', $tenantId)->delete();
                });
            } else {
                DB::table('tenants')->where('id', $tenantId)->update([
                    'is_active' => 0,
                    'updated_at' => now(),
                ]);
            }

            // Audit
            SuperAdminAuditService::log(
                'tenant_deleted',
                'tenant',
                $tenantId,
                $tenant->name,
                ['is_active' => $tenant->is_active],
                ['hard_delete' => $hardDelete],
                ($hardDelete ? 'Hard deleted' : 'Soft deleted (deactivated)') . " tenant '{$tenant->name}'"
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('TenantHierarchyService::deleteTenant failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to delete tenant: ' . $e->getMessage()];
        }
    }

    /**
     * Move a tenant to a new parent in the hierarchy.
     *
     * @return array{success: bool, error?: string}
     */
    public static function moveTenant(int $tenantId, int $newParentId): array
    {
        try {
            if ($tenantId === 1) {
                return ['success' => false, 'error' => 'Cannot move the Master tenant'];
            }

            if ($tenantId === $newParentId) {
                return ['success' => false, 'error' => 'Cannot move a tenant to be its own parent'];
            }

            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if (!$tenant) {
                return ['success' => false, 'error' => 'Tenant not found'];
            }

            $newParent = DB::table('tenants')->where('id', $newParentId)->first();
            if (!$newParent) {
                return ['success' => false, 'error' => 'New parent tenant not found'];
            }

            // Prevent circular hierarchy: new parent cannot be a descendant of this tenant
            $tenantPath = $tenant->path ?? ('/' . $tenantId . '/');
            $newParentPath = $newParent->path ?? ('/' . $newParentId . '/');
            if (str_starts_with($newParentPath, $tenantPath)) {
                return ['success' => false, 'error' => 'Cannot move a tenant under one of its own descendants'];
            }

            $oldParentId = $tenant->parent_id;
            $oldPath = $tenant->path;
            $newDepth = ($newParent->depth ?? 0) + 1;
            $newPath = ($newParent->path ?? ('/' . $newParentId . '/')) . $tenantId . '/';

            DB::table('tenants')->where('id', $tenantId)->update([
                'parent_id'  => $newParentId,
                'depth'      => $newDepth,
                'path'       => $newPath,
                'updated_at' => now(),
            ]);

            // Update materialized paths and depth for all descendants
            if ($oldPath) {
                $descendants = DB::table('tenants')
                    ->where('path', 'LIKE', $oldPath . '%')
                    ->where('id', '!=', $tenantId)
                    ->get();

                foreach ($descendants as $desc) {
                    $updatedPath = str_replace($oldPath, $newPath, $desc->path);
                    $updatedDepth = substr_count(trim($updatedPath, '/'), '/');
                    DB::table('tenants')->where('id', $desc->id)->update([
                        'path'  => $updatedPath,
                        'depth' => $updatedDepth,
                    ]);
                }
            }

            // Audit
            SuperAdminAuditService::log(
                'tenant_moved',
                'tenant',
                $tenantId,
                $tenant->name,
                ['parent_id' => $oldParentId, 'path' => $oldPath],
                ['parent_id' => $newParentId, 'path' => $newPath],
                "Moved tenant '{$tenant->name}' from parent ID {$oldParentId} to {$newParentId}"
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('TenantHierarchyService::moveTenant failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to move tenant: ' . $e->getMessage()];
        }
    }

    /**
     * Enable or disable sub-tenant (Hub) capability for a tenant.
     *
     * @return array{success: bool, error?: string}
     */
    public static function toggleSubtenantCapability(int $tenantId, bool $enable): array
    {
        try {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if (!$tenant) {
                return ['success' => false, 'error' => 'Tenant not found'];
            }

            $oldValue = (bool) $tenant->allows_subtenants;

            DB::table('tenants')->where('id', $tenantId)->update([
                'allows_subtenants' => $enable ? 1 : 0,
                'max_depth'         => $enable ? max((int) $tenant->max_depth, 2) : 0,
                'updated_at'        => now(),
            ]);

            // If disabling, revoke super admin from all users of this tenant
            if (!$enable) {
                DB::table('users')
                    ->where('tenant_id', $tenantId)
                    ->where('is_tenant_super_admin', 1)
                    ->update(['is_tenant_super_admin' => 0]);
            }

            // Audit
            SuperAdminAuditService::log(
                'hub_toggled',
                'tenant',
                $tenantId,
                $tenant->name,
                ['allows_subtenants' => $oldValue],
                ['allows_subtenants' => $enable],
                ($enable ? 'Enabled' : 'Disabled') . " Hub mode for tenant '{$tenant->name}'"
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('TenantHierarchyService::toggleSubtenantCapability failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to toggle sub-tenant capability: ' . $e->getMessage()];
        }
    }

    /**
     * Grant tenant super admin privileges to a user.
     *
     * @return array{success: bool, error?: string}
     */
    public static function assignTenantSuperAdmin(int $userId, int $tenantId): array
    {
        try {
            $user = DB::table('users')->where('id', $userId)->first();
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            if ((int) $user->tenant_id !== $tenantId) {
                return ['success' => false, 'error' => 'User does not belong to this tenant'];
            }

            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if (!$tenant || !$tenant->allows_subtenants) {
                return ['success' => false, 'error' => 'Tenant does not support sub-tenants'];
            }

            DB::table('users')->where('id', $userId)->update([
                'is_tenant_super_admin' => 1,
            ]);

            $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            SuperAdminAuditService::log(
                'super_admin_granted',
                'user',
                $userId,
                $userName,
                ['is_tenant_super_admin' => 0],
                ['is_tenant_super_admin' => 1],
                "Granted Tenant Super Admin to '{$userName}' in tenant '{$tenant->name}'"
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('TenantHierarchyService::assignTenantSuperAdmin failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to grant super admin: ' . $e->getMessage()];
        }
    }

    /**
     * Revoke tenant super admin privileges from a user.
     *
     * @return array{success: bool, error?: string}
     */
    public static function revokeTenantSuperAdmin(int $userId): array
    {
        try {
            $user = DB::table('users')->where('id', $userId)->first();
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            DB::table('users')->where('id', $userId)->update([
                'is_tenant_super_admin' => 0,
            ]);

            $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            SuperAdminAuditService::log(
                'super_admin_revoked',
                'user',
                $userId,
                $userName,
                ['is_tenant_super_admin' => 1],
                ['is_tenant_super_admin' => 0],
                "Revoked Tenant Super Admin from '{$userName}'"
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('TenantHierarchyService::revokeTenantSuperAdmin failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to revoke super admin: ' . $e->getMessage()];
        }
    }

    /**
     * Seed required default data for a newly created tenant.
     *
     * Seeds: tenant_settings (security defaults), default categories,
     * and federation tables. This ensures a new tenant is fully functional
     * from the moment it is created.
     */
    private static function seedTenantDefaults(int $tenantId): void
    {
        // 1. Seed secure default tenant_settings
        //    admin_approval=true and email_verification=true ensure new signups
        //    require admin review and verified email — secure by default.
        $defaultSettings = [
            ['tenant_id' => $tenantId, 'setting_key' => 'general.registration_mode', 'setting_value' => 'open', 'setting_type' => 'string'],
            ['tenant_id' => $tenantId, 'setting_key' => 'general.admin_approval', 'setting_value' => 'true', 'setting_type' => 'boolean'],
            ['tenant_id' => $tenantId, 'setting_key' => 'general.email_verification', 'setting_value' => 'true', 'setting_type' => 'boolean'],
            ['tenant_id' => $tenantId, 'setting_key' => 'general.maintenance_mode', 'setting_value' => 'false', 'setting_type' => 'boolean'],
        ];

        foreach ($defaultSettings as $setting) {
            DB::table('tenant_settings')->insertOrIgnore($setting);
        }

        // 2. Seed default categories (universal set for any timebank)
        $categories = [
            'Home & Garden',
            'Technology',
            'Education & Tutoring',
            'Health & Wellness',
            'Transport',
            'Creative & Arts',
            'Professional Services',
            'Community',
        ];

        foreach ($categories as $sort => $categoryName) {
            DB::table('categories')->insertOrIgnore([
                'tenant_id'  => $tenantId,
                'name'       => $categoryName,
                'slug'       => strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($categoryName)))),
                'sort_order' => $sort,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Seed federation tenant features (required for federation feature gating)
        $federationFeatures = [
            'tenant_federation_enabled',
            'tenant_appear_in_directory',
            'tenant_profiles_enabled',
            'tenant_messaging_enabled',
            'tenant_transactions_enabled',
            'tenant_listings_enabled',
            'tenant_events_enabled',
            'tenant_groups_enabled',
        ];

        foreach ($federationFeatures as $featureKey) {
            DB::table('federation_tenant_features')->insertOrIgnore([
                'tenant_id'   => $tenantId,
                'feature_key' => $featureKey,
                'is_enabled'  => 1,
                'updated_at'  => now(),
            ]);
        }
    }

    /**
     * Generate a URL-friendly slug from a tenant name.
     */
    private static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Ensure uniqueness by appending a number if necessary
        $base = $slug;
        $counter = 1;
        while (DB::table('tenants')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
