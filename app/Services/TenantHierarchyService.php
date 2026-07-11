<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Services\TenantFeatureConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
     * Normalize and validate routing fields shared by tenant-management and
     * general-settings endpoints. A malformed/reserved/duplicate route key can
     * make both tenant resolution and authoritative prerendering ambiguous.
     *
     * @return array{success:bool,data?:array<string,string|null>,error?:string}
     */
    public static function validateRoutingUpdate(int $tenantId, array $data, ?object $tenant = null): array
    {
        $tenant ??= DB::table('tenants')
            ->where('id', $tenantId)
            ->select('id', 'slug', 'domain', 'accessible_domain')
            ->first();
        if (!$tenant) {
            return ['success' => false, 'error' => 'Tenant not found'];
        }

        $normalized = [];
        if (array_key_exists('slug', $data)) {
            $slug = strtolower(trim((string) $data['slug']));
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
                return ['success' => false, 'error' => __('api.slug_format_invalid')];
            }
            if (\App\Core\TenantContext::isReservedPathSegment($slug)) {
                return ['success' => false, 'error' => __('api.slug_reserved_short', ['slug' => $slug])];
            }
            if (DB::table('tenants')->where('slug', $slug)->where('id', '!=', $tenantId)->exists()) {
                return ['success' => false, 'error' => "Slug '{$slug}' is already in use"];
            }
            $normalized['slug'] = $slug;
        }

        foreach (['domain', 'accessible_domain'] as $field) {
            if (!array_key_exists($field, $data)) continue;
            $host = strtolower(rtrim(trim((string) ($data[$field] ?? '')), '.'));
            if ($host !== ''
                && !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/', $host)) {
                return ['success' => false, 'error' => __('api.domain_format_invalid')];
            }
            if ($host !== '' && self::isReservedPlatformHost($host)) {
                return ['success' => false, 'error' => __('api.domain_format_invalid')];
            }
            $normalized[$field] = $host === '' ? null : $host;
        }

        $effectiveDomain = array_key_exists('domain', $normalized)
            ? (string) ($normalized['domain'] ?? '')
            : strtolower(rtrim((string) ($tenant->domain ?? ''), '.'));
        $effectiveAccessible = array_key_exists('accessible_domain', $normalized)
            ? (string) ($normalized['accessible_domain'] ?? '')
            : strtolower(rtrim((string) ($tenant->accessible_domain ?? ''), '.'));
        if ($effectiveDomain !== '' && $effectiveAccessible !== ''
            && strcasecmp($effectiveDomain, $effectiveAccessible) === 0) {
            return ['success' => false, 'error' => 'Primary and accessible domains must be different'];
        }

        foreach (['domain', 'accessible_domain'] as $field) {
            $host = $normalized[$field] ?? null;
            if ($host !== null && DB::table('tenants')
                ->where('id', '!=', $tenantId)
                ->where(function ($query) use ($host) {
                    $query->where('domain', $host)->orWhere('accessible_domain', $host);
                })
                ->exists()) {
                return ['success' => false, 'error' => "Domain '{$host}' is already in use"];
            }
        }

        return ['success' => true, 'data' => $normalized];
    }

    /** Core service hosts can never be assigned as a tenant custom domain. */
    private static function isReservedPlatformHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) return true;

        $reserved = [
            'project-nexus.ie',
            'www.project-nexus.ie',
            'app.project-nexus.ie',
            'api.project-nexus.ie',
            'accessible.project-nexus.ie',
        ];
        foreach ([
            config('app.url'),
            config('app.frontend_url'),
            config('app.accessible_frontend_url'),
            config('app.sales_site_url'),
        ] as $url) {
            if (!is_string($url) || trim($url) === '') continue;
            $configuredHost = parse_url($url, PHP_URL_HOST);
            if (is_string($configuredHost) && $configuredHost !== '') {
                $reserved[] = strtolower(rtrim($configuredHost, '.'));
            }
        }

        return in_array($host, array_unique($reserved), true);
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
            $slug = strtolower(trim($data['slug'] ?? ''));
            if (empty($slug)) {
                $slug = self::generateSlug($name);
            }
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
                return ['success' => false, 'error' => __('api.slug_format_invalid')];
            }
            if (\App\Core\TenantContext::isReservedPathSegment($slug)) {
                return ['success' => false, 'error' => __('api.slug_reserved_short', ['slug' => $slug])];
            }

            // Check slug uniqueness
            $existingSlug = DB::table('tenants')->where('slug', $slug)->exists();
            if ($existingSlug) {
                return ['success' => false, 'error' => "Slug '{$slug}' is already in use"];
            }

            // Check domain uniqueness if provided. The React custom domain
            // (domain) and the accessible/GOV.UK custom domain (accessible_domain)
            // share ONE global hostname namespace — a host may belong to exactly
            // one tenant, on exactly one of the two columns.
            $domain = strtolower(rtrim(trim($data['domain'] ?? ''), '.'));
            $accessibleDomain = strtolower(rtrim(trim($data['accessible_domain'] ?? ''), '.'));

            foreach ([$domain, $accessibleDomain] as $host) {
                if ($host !== '' && !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/', $host)) {
                    return ['success' => false, 'error' => __('api.domain_format_invalid')];
                }
                if ($host !== '' && self::isReservedPlatformHost($host)) {
                    return ['success' => false, 'error' => __('api.domain_format_invalid')];
                }
            }

            if ($domain !== '' && $accessibleDomain !== '' && strcasecmp($domain, $accessibleDomain) === 0) {
                return ['success' => false, 'error' => 'Primary and accessible domains must be different'];
            }

            foreach ([$domain, $accessibleDomain] as $host) {
                if ($host === '') {
                    continue;
                }
                $inUse = DB::table('tenants')
                    ->where(function ($q) use ($host) {
                        $q->where('domain', $host)->orWhere('accessible_domain', $host);
                    })
                    ->exists();
                if ($inUse) {
                    return ['success' => false, 'error' => "Domain '{$host}' is already in use"];
                }
            }

            // Handle features — store as JSON
            // If no features provided, use TenantFeatureConfig::FEATURE_DEFAULTS as the
            // single source of truth. This keeps new-tenant defaults in sync automatically.
            $features = null;
            if (isset($data['features'])) {
                $features = is_string($data['features'])
                    ? $data['features']
                    : json_encode($data['features']);
            } else {
                $features = json_encode(TenantFeatureConfig::FEATURE_DEFAULTS);
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
                $name, $slug, $domain, $accessibleDomain, $data, $parentId, $newDepth, $features, $configuration, $parent
            ) {
                $tenantId = DB::table('tenants')->insertGetId([
                    'name'               => $name,
                    'slug'               => $slug,
                    'domain'             => $domain ?: null,
                    'accessible_domain'  => $accessibleDomain ?: null,
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
                    'og_image_url'          => $data['og_image_url'] ?? null,
                    'robots_directive'      => $data['robots_directive'] ?? 'index, follow',
                    'seo_organization_type' => $data['seo_organization_type'] ?? null,
                    // Location fields
                    'location_name'      => $data['location_name'] ?? null,
                    'country_code'       => $data['country_code'] ?? null,
                    'service_area'       => $data['service_area'] ?? 'national',
                    'latitude'           => $data['latitude'] ?? null,
                    'longitude'          => $data['longitude'] ?? null,
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

            self::bustBootstrapCache($parentId);
            if ((int) ($data['is_active'] ?? 1) === 1) {
                app(PrerenderContentInvalidator::class)->refreshTenant($tenantId);
            }

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

            $routingInput = array_intersect_key(
                $data,
                array_flip(['slug', 'domain', 'accessible_domain'])
            );
            if ($routingInput !== []) {
                $routingValidation = self::validateRoutingUpdate($tenantId, $routingInput, $tenant);
                if (!$routingValidation['success']) {
                    return ['success' => false, 'error' => $routingValidation['error'] ?? __('api.no_valid_fields_to_update')];
                }
                $data = array_replace($data, $routingValidation['data'] ?? []);
            }

            // A host cannot serve as BOTH the React and the accessible frontend of
            // the same tenant. Validate against the effective post-update values.
            $effectiveDomain = array_key_exists('domain', $data)
                ? strtolower(rtrim(trim((string) $data['domain']), '.'))
                : strtolower(rtrim((string) ($tenant->domain ?? ''), '.'));
            $effectiveAccessible = array_key_exists('accessible_domain', $data)
                ? strtolower(rtrim(trim((string) $data['accessible_domain']), '.'))
                : strtolower(rtrim((string) ($tenant->accessible_domain ?? ''), '.'));
            if ($effectiveDomain !== '' && $effectiveAccessible !== ''
                && strcasecmp($effectiveDomain, $effectiveAccessible) === 0) {
                return ['success' => false, 'error' => 'Primary and accessible domains must be different'];
            }

            // Build update array from allowed fields
            $allowed = [
                'name', 'slug', 'domain', 'accessible_domain', 'tagline', 'description',
                'allows_subtenants', 'max_depth', 'is_active', 'features', 'configuration',
                'contact_email', 'contact_phone', 'address',
                'meta_title', 'meta_description', 'h1_headline', 'hero_intro',
                'og_image_url', 'robots_directive', 'seo_organization_type',
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
                if ($field === 'slug') {
                    $value = strtolower(trim((string) $value));
                    if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $value)) {
                        return ['success' => false, 'error' => __('api.slug_format_invalid')];
                    }
                    if (\App\Core\TenantContext::isReservedPathSegment($value)) {
                        return ['success' => false, 'error' => __('api.slug_reserved_short', ['slug' => $value])];
                    }
                }
                if ($field === 'slug' && $value !== ($tenant->slug ?? '')) {
                    $existingSlug = DB::table('tenants')
                        ->where('slug', $value)
                        ->where('id', '!=', $tenantId)
                        ->exists();
                    if ($existingSlug) {
                        return ['success' => false, 'error' => "Slug '{$value}' is already in use"];
                    }
                }

                // Normalize empty domains to NULL so the UNIQUE indexes accept
                // multiple "no custom domain" tenants — MySQL allows many NULLs
                // in a unique index but not many empty strings.
                if (in_array($field, ['domain', 'accessible_domain'], true)) {
                    $value = ($value === '' || $value === null)
                        ? null
                        : strtolower(rtrim(trim((string) $value), '.'));
                    if ($value !== null
                        && !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/', $value)) {
                        return ['success' => false, 'error' => __('api.domain_format_invalid')];
                    }
                }

                // Check cross-column domain uniqueness if being changed. A host
                // must be unique across BOTH domain and accessible_domain.
                if (in_array($field, ['domain', 'accessible_domain'], true)
                    && !empty($value) && $value !== ($tenant->$field ?? null)) {
                    $inUse = DB::table('tenants')
                        ->where('id', '!=', $tenantId)
                        ->where(function ($q) use ($value) {
                            $q->where('domain', $value)->orWhere('accessible_domain', $value);
                        })
                        ->exists();
                    if ($inUse) {
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
            $routingFields = ['slug', 'domain', 'is_active'];
            $routingChanged = array_intersect(array_keys($update), $routingFields) !== [];
            $purgeUnexpected = array_intersect(array_keys($update), ['features', 'configuration']) !== [];

            DB::transaction(function () use (
                $tenantId,
                $tenant,
                $update,
                $routingChanged,
                $purgeUnexpected
            ): void {
                DB::table('tenants')->where('id', $tenantId)->update($update);

                $invalidator = app(PrerenderContentInvalidator::class);
                if ($routingChanged) {
                    // Old hosts/prefixes can only be removed by a complete
                    // generation whose queue intent commits with this update.
                    $invalidator->refreshAllOrFail();
                } elseif ((int) ($tenant->is_active ?? 0) === 1) {
                    $invalidator->refreshTenantOrFail($tenantId, true, $purgeUnexpected);
                }
            });

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

            // Bootstrap cache invalidation.
            // Always bust the tenant itself (name, slug, branding, etc. may have changed).
            // If the domain changed, also bust all direct children: they cache a parent_domain
            // field that references this tenant's domain — their cached value is now stale.
            $idsToFlush = [$tenantId];
            $parentSwitcherFields = ['name', 'slug', 'domain', 'tagline', 'is_active'];
            if (!empty($tenant->parent_id) && array_intersect(array_keys($update), $parentSwitcherFields)) {
                $idsToFlush[] = (int) $tenant->parent_id;
            }
            if (array_key_exists('domain', $update)) {
                $childIds = DB::table('tenants')
                    ->where('parent_id', $tenantId)
                    ->where('is_active', 1)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();
                $idsToFlush = array_merge($idsToFlush, $childIds);
            }
            self::bustBootstrapCache(...$idsToFlush);

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

            DB::transaction(function () use ($tenantId, $tenant, $hardDelete): void {
                if ($hardDelete) {
                    // WARNING: This only moves users and child tenants. Other tenant-scoped
                    // data (listings, transactions, messages, events, groups, etc.) will be
                    // orphaned. Hard delete should only be used for empty/test tenants.
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
                } else {
                    DB::table('tenants')->where('id', $tenantId)->update([
                        'is_active' => 0,
                        'updated_at' => now(),
                    ]);
                }

                app(PrerenderContentInvalidator::class)->refreshAllOrFail();
            });

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

            self::bustBootstrapCache($tenantId, (int) ($tenant->parent_id ?? 0));

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

            // Prevent circular hierarchy: new parent cannot be a descendant of this tenant.
            // Requires both path fields to be populated — null means the tree is corrupt;
            // fail safe rather than allowing an undetectable cycle.
            if (empty($tenant->path) || empty($newParent->path)) {
                return ['success' => false, 'error' => 'Cannot move tenant: hierarchy path data is missing. Re-save the affected tenants to rebuild paths.'];
            }
            if (str_starts_with($newParent->path, $tenant->path)) {
                return ['success' => false, 'error' => 'Cannot move a tenant under one of its own descendants'];
            }

            $oldParentId = $tenant->parent_id;
            $oldPath = $tenant->path;
            $newDepth = ($newParent->depth ?? 0) + 1;
            $newPath = ($newParent->path ?? ('/' . $newParentId . '/')) . $tenantId . '/';

            DB::transaction(function () use (
                $tenantId,
                $newParentId,
                $newDepth,
                $newPath,
                $oldPath
            ): void {
                DB::table('tenants')->where('id', $tenantId)->update([
                    'parent_id'  => $newParentId,
                    'depth'      => $newDepth,
                    'path'       => $newPath,
                    'updated_at' => now(),
                ]);

                // Update materialized paths and depth for all descendants.
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

                app(PrerenderContentInvalidator::class)->refreshAllOrFail();
            });

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

            // The moved tenant's parent_domain changed, and both parent switcher lists changed.
            self::bustBootstrapCache($tenantId, (int) ($oldParentId ?? 0), $newParentId);

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
     * federation tables, AI module docs, default member attributes, and
     * default navigation menus. This ensures a new tenant is fully functional
     * from the moment it is created.
     *
     * Note on skills: the `skills` table is intentionally NOT seeded here.
     * It is populated through member activity (offers/requests), never by
     * provisioning — every healthy tenant (incl. tenant 2 'hour-timebank')
     * has zero rows in `skills` at creation. The historical "seeds skills"
     * note referred to the separate `skill_categories` taxonomy, not this
     * table, so there is nothing to restore here.
     */
    private static function seedTenantDefaults(int $tenantId): void
    {
        // Re-read the tenant row for compatibility with the provisioning shape.
        // Safeguarding jurisdiction is always a separate explicit decision;
        // country code never chooses a criminal-record checking regime.
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $countryCode = strtoupper((string) ($tenant->country_code ?? ''));
        $presetKey = self::mapCountryCodeToPreset($countryCode);

        // 1. Seed secure default tenant_settings
        //    admin_approval=true and email_verification=true ensure new signups
        //    require admin review and verified email — secure by default.
        //
        //    The optional safeguarding step is available by default, but its
        //    jurisdiction preset remains custom until an administrator makes an
        //    explicit choice. Country alone never selects a police-checking
        //    regime.
        $defaultSettings = [
            ['tenant_id' => $tenantId, 'setting_key' => 'general.registration_mode', 'setting_value' => 'open', 'setting_type' => 'string'],
            // Bare key — matches what TenantSettingsService::requiresAdminApproval()
            // and AdminConfigController read/write. The historical `general.`
            // prefix was orphaned (reader never looked it up).
            ['tenant_id' => $tenantId, 'setting_key' => 'admin_approval', 'setting_value' => 'true', 'setting_type' => 'boolean'],
            ['tenant_id' => $tenantId, 'setting_key' => 'email_verification', 'setting_value' => 'true', 'setting_type' => 'boolean'],
            ['tenant_id' => $tenantId, 'setting_key' => 'general.maintenance_mode', 'setting_value' => 'false', 'setting_type' => 'boolean'],
            // SEO defaults — ensure every new tenant has sitemap, canonical, OG, and Twitter cards enabled
            ['tenant_id' => $tenantId, 'setting_key' => 'seo_auto_sitemap', 'setting_value' => '1', 'setting_type' => 'boolean'],
            ['tenant_id' => $tenantId, 'setting_key' => 'seo_canonical_urls', 'setting_value' => '1', 'setting_type' => 'boolean'],
            ['tenant_id' => $tenantId, 'setting_key' => 'seo_open_graph', 'setting_value' => '1', 'setting_type' => 'boolean'],
            ['tenant_id' => $tenantId, 'setting_key' => 'seo_twitter_cards', 'setting_value' => '1', 'setting_type' => 'boolean'],
            // Onboarding defaults — safeguarding step ON by default so new
            // communities surface vulnerability/vetting declarations to members.
            ['tenant_id' => $tenantId, 'setting_key' => 'onboarding.step_safeguarding_enabled', 'setting_value' => '1', 'setting_type' => 'boolean'],
            ['tenant_id' => $tenantId, 'setting_key' => 'onboarding.step_safeguarding_required', 'setting_value' => '0', 'setting_type' => 'boolean'],
            ['tenant_id' => $tenantId, 'setting_key' => 'onboarding.country_preset', 'setting_value' => $presetKey, 'setting_type' => 'string'],
        ];

        foreach ($defaultSettings as $setting) {
            DB::table('tenant_settings')->insertOrIgnore($setting);
        }

        // A preset is applied only after an administrator explicitly selects a
        // safeguarding jurisdiction. New tenants therefore remain custom here.
        if ($presetKey !== 'custom') {
            try {
                SafeguardingPreferenceService::applyCountryPreset($tenantId, $presetKey);
            } catch (\Throwable $e) {
                Log::warning('TenantHierarchyService: applyCountryPreset failed during seed', [
                    'tenant_id' => $tenantId,
                    'preset' => $presetKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. Seed default categories (universal set for any timebank).
        //    Shared with the provisioning path via TenantDefaultsSeeder.
        //    Non-fatal — consistent with the attribute/menu seeders below.
        try {
            TenantDefaultsSeeder::seedCategories($tenantId);
        } catch (\Throwable $e) {
            Log::warning('TenantHierarchyService: seedCategories failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
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

        // 4. Seed AI module docs (out-of-the-box AI training for the assistant).
        //    Covers platform fundamentals, every feature/module, account/privacy,
        //    accessibility, mobile and troubleshooting. Admins can edit or add
        //    their own custom docs afterwards. Idempotent — won't overwrite
        //    existing docs sharing the same slug. Failure is non-fatal (the
        //    chat falls back to its built-in defaults).
        try {
            (new \App\Services\AI\AiModuleDocsService())->seedDefaultsForTenant($tenantId);
        } catch (\Throwable $e) {
            Log::warning('TenantHierarchyService: seedDefaultsForTenant (AI module docs) failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }

        // 5. Seed default member attributes (offer/request filters). Restores a
        //    Laravel-migration regression — historically every new tenant was
        //    seeded with this set. Shared with the provisioning path via
        //    TenantDefaultsSeeder. Failure is non-fatal.
        try {
            TenantDefaultsSeeder::seedAttributes($tenantId);
        } catch (\Throwable $e) {
            Log::warning('TenantHierarchyService: seedAttributes failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }

        // 6. Seed default navigation menus (header + footer). Restores a
        //    Laravel-migration regression — historically every new tenant was
        //    seeded with these menus. Shared with the provisioning path via
        //    TenantDefaultsSeeder. Failure is non-fatal.
        try {
            TenantDefaultsSeeder::seedMenus($tenantId);
        } catch (\Throwable $e) {
            Log::warning('TenantHierarchyService: seedMenus failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Country code must never imply a safeguarding jurisdiction.
     */
    private static function mapCountryCodeToPreset(string $countryCode): string
    {
        return 'custom';
    }

    /**
     * Invalidate the bootstrap cache for one or more tenants.
     *
     * The cache key format mirrors RedisCache::buildKey(): "t{id}:tenant_bootstrap".
     * Called after any mutation that changes hierarchy-derived data:
     *   - createTenant/deleteTenant: bust the parent tenant's child switcher list
     *   - moveTenant: bust the moved tenant (its parent_domain changes)
     *     plus old/new parents (their switcher lists change)
     *   - updateTenant with child display or routing fields: bust the parent
     *   - updateTenant with domain change: bust the tenant + all direct children
     *     (direct children expose parent_domain pointing to this tenant's domain)
     */
    private static function bustBootstrapCache(int ...$tenantIds): void
    {
        foreach (array_unique(array_filter($tenantIds, fn (int $id): bool => $id > 0)) as $id) {
            try {
                Cache::store('redis')->forget("t{$id}:tenant_bootstrap");
            } catch (\Throwable $e) {
                Log::warning("TenantHierarchyService: failed to bust bootstrap cache for tenant {$id}", [
                    'error' => $e->getMessage(),
                ]);
            }
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
