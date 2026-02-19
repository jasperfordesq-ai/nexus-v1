<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\RedisCache;
use Nexus\Services\BrokerControlConfigService;
use Nexus\Helpers\UrlHelper;

/**
 * TenantBootstrapController - Public tenant configuration endpoint
 *
 * Provides tenant-specific branding, features, and configuration
 * for frontend applications (React, mobile apps, SPAs).
 *
 * This endpoint is PUBLIC (no authentication required) and should be
 * called by frontends on initial load to configure the application.
 *
 * Tenant resolution uses the existing TenantContext mechanism:
 * 1. Domain (HTTP_HOST)
 * 2. X-Tenant-ID header
 * 3. Bearer token tenant_id claim
 *
 * Response is cached for 10 minutes to reduce database load.
 *
 * @package Nexus\Controllers\Api
 */
class TenantBootstrapController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * Cache TTL for tenant bootstrap data (10 minutes)
     */
    private const CACHE_TTL = 600;

    /**
     * Cache key prefix for bootstrap data
     */
    private const CACHE_KEY = 'tenant_bootstrap';

    /**
     * GET /api/v2/tenant/bootstrap
     *
     * Returns tenant configuration for frontend initialization.
     *
     * Headers:
     *   X-Tenant-ID: (optional) - Explicit tenant ID
     *
     * Query Parameters:
     *   slug: (optional) - Tenant slug for explicit lookup (takes priority)
     *
     * Response: 200 OK with tenant data
     */
    public function bootstrap(): void
    {
        // Check for explicit slug query parameter (used by React frontend for tenant switching)
        $slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;

        if ($slug !== null && $slug !== '') {
            // Look up tenant by slug
            $db = \Nexus\Core\Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM tenants WHERE slug = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$slug]);
            $slugTenant = $stmt->fetch();

            if (!$slugTenant) {
                // TRS-001 fail-closed: unknown slug MUST 404, never fall back to master tenant
                $this->respondWithError(
                    'TENANT_NOT_FOUND',
                    "Community '{$slug}' was not found or is inactive.",
                    null,
                    404
                );
                return;
            }

            $tenantId = (int) $slugTenant['id'];

            // Set TenantContext so downstream calls (e.g. BrokerControlConfigService)
            // read config for the correct tenant, not the API domain's tenant
            TenantContext::setById($tenantId);

            // Try cache first
            $cached = RedisCache::get(self::CACHE_KEY, $tenantId);
            if ($cached !== null) {
                $this->respondWithData($cached);
                return;
            }

            $data = $this->buildBootstrapData($slugTenant);
            RedisCache::set(self::CACHE_KEY, $data, self::CACHE_TTL, $tenantId);
            $this->respondWithData($data);
            return;
        }

        // Origin-based resolution for custom domains (e.g., hour-timebank.ie)
        // When the React SPA is on a tenant's custom domain and calls the API at
        // api.project-nexus.ie, TenantContext::resolve() sees HTTP_HOST=api.project-nexus.ie
        // and falls back to master tenant. The Origin header tells us the real domain.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            if ($originHost) {
                $originHost = preg_replace('/^www\./', '', $originHost);
                $db = \Nexus\Core\Database::getConnection();
                $stmt = $db->prepare("SELECT * FROM tenants WHERE domain = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$originHost]);
                $originTenant = $stmt->fetch();

                if ($originTenant && (int) $originTenant['id'] !== 1) {
                    $tenantId = (int) $originTenant['id'];

                    // Set TenantContext so downstream calls read correct tenant config
                    TenantContext::setById($tenantId);

                    $cached = RedisCache::get(self::CACHE_KEY, $tenantId);
                    if ($cached !== null) {
                        $this->respondWithData($cached);
                        return;
                    }

                    $data = $this->buildBootstrapData($originTenant);
                    RedisCache::set(self::CACHE_KEY, $data, self::CACHE_TTL, $tenantId);
                    $this->respondWithData($data);
                    return;
                }
            }
        }

        // Default: use TenantContext resolved by index.php (header/domain/token/fallback)
        $tenantId = TenantContext::getId();

        // Try to get from cache first
        $cacheKey = self::CACHE_KEY;
        $cached = RedisCache::get($cacheKey, $tenantId);

        if ($cached !== null) {
            $this->respondWithData($cached);
            return;
        }

        // Build bootstrap data from tenant context
        $tenant = TenantContext::get();
        $data = $this->buildBootstrapData($tenant);

        // Cache the result
        RedisCache::set($cacheKey, $data, self::CACHE_TTL, $tenantId);

        $this->respondWithData($data);
    }

    /**
     * Build bootstrap data from tenant record
     *
     * Only exposes PUBLIC configuration - never include:
     * - API keys or secrets
     * - Internal admin emails
     * - Database credentials
     * - Any sensitive configuration
     *
     * @param array $tenant Tenant record from database
     * @return array Public bootstrap data
     */
    private function buildBootstrapData(array $tenant): array
    {
        // Parse JSON configuration
        $config = $this->parseJson($tenant['configuration'] ?? null);
        $features = $this->parseJson($tenant['features'] ?? null);

        // Build response structure
        $data = [
            'id' => (int) $tenant['id'],
            'name' => $tenant['name'] ?? '',
            'slug' => $tenant['slug'] ?? '',
        ];

        // Domain (only if set)
        if (!empty($tenant['domain'])) {
            $data['domain'] = $tenant['domain'];
        }

        // Tagline (optional)
        if (!empty($tenant['tagline'])) {
            $data['tagline'] = $tenant['tagline'];
        }

        // Default layout/theme
        $data['default_layout'] = $tenant['default_layout'] ?? 'modern';

        // Branding section
        $data['branding'] = $this->buildBrandingData($tenant, $config);

        // Feature flags (optional features that can be toggled per tenant)
        $data['features'] = $this->buildFeaturesData($features);

        // Module configuration (core modules that can be disabled per tenant)
        $data['modules'] = $this->buildModulesData($config);

        // SEO metadata
        $data['seo'] = $this->buildSeoData($tenant);

        // Public configuration (footer, etc.)
        $publicConfig = $this->buildPublicConfig($config);
        if (!empty($publicConfig)) {
            $data['config'] = $publicConfig;
        }

        // Contact information (only if public)
        $contact = $this->buildContactData($tenant);
        if (!empty($contact)) {
            $data['contact'] = $contact;
        }

        // Social links
        $social = $this->buildSocialData($tenant);
        if (!empty($social)) {
            $data['social'] = $social;
        }

        // General settings (maintenance mode, registration settings, etc.)
        $data['settings'] = $this->buildGeneralSettings((int) $tenant['id']);

        return $data;
    }

    /**
     * Build general settings from tenant_settings table
     */
    private function buildGeneralSettings(int $tenantId): array
    {
        $settings = [];

        try {
            // Fetch general.* settings from tenant_settings table
            $rows = Database::query(
                "SELECT setting_key, setting_value FROM tenant_settings
                 WHERE tenant_id = ? AND setting_key LIKE 'general.%'",
                [$tenantId]
            )->fetchAll();

            foreach ($rows as $row) {
                $key = str_replace('general.', '', $row['setting_key']);
                $value = $row['setting_value'];

                // Convert boolean strings to actual booleans
                if ($value === 'true' || $value === '1') {
                    $settings[$key] = true;
                } elseif ($value === 'false' || $value === '0') {
                    $settings[$key] = false;
                } else {
                    $settings[$key] = $value;
                }
            }
        } catch (\Exception $e) {
            // tenant_settings table may not exist yet - return empty settings
        }

        return $settings;
    }

    /**
     * Build branding data (logos, colors)
     */
    private function buildBrandingData(array $tenant, ?array $config): array
    {
        $branding = [];

        // Logo URL - check common locations
        // Note: We don't expose internal file paths, only public URLs
        if (!empty($tenant['logo_url'])) {
            $branding['logo_url'] = UrlHelper::absolute($tenant['logo_url']);
        } elseif (!empty($config['logo_url'])) {
            $branding['logo_url'] = UrlHelper::absolute($config['logo_url']);
        }

        // Favicon
        if (!empty($tenant['favicon_url'])) {
            $branding['favicon_url'] = UrlHelper::absolute($tenant['favicon_url']);
        } elseif (!empty($config['favicon_url'])) {
            $branding['favicon_url'] = UrlHelper::absolute($config['favicon_url']);
        }

        // Primary color (CSS variable or hex)
        if (!empty($tenant['primary_color'])) {
            $branding['primary_color'] = $tenant['primary_color'];
        } elseif (!empty($config['primary_color'])) {
            $branding['primary_color'] = $config['primary_color'];
        }

        // OG image for social sharing
        if (!empty($tenant['og_image_url'])) {
            $branding['og_image_url'] = UrlHelper::absolute($tenant['og_image_url']);
        }

        return $branding;
    }

    /**
     * Build feature flags indicating what modules are enabled
     */
    private function buildFeaturesData(?array $features): array
    {
        // Default feature set - most features enabled by default
        $defaults = [
            'listings' => true,
            'events' => true,
            'groups' => true,
            'wallet' => true,
            'messages' => true,
            'feed' => true,
            'notifications' => true,
            'search' => true,
            'connections' => true,
            'reviews' => true,
            'gamification' => false,
            'volunteering' => false,
            'federation' => false,
            'blog' => true,
            'resources' => false,
            'goals' => false,
            'polls' => false,
            'exchange_workflow' => false,
        ];

        if ($features === null) {
            $features = [];
        }

        // Merge with defaults (explicit false should override default true)
        $result = [];
        foreach ($defaults as $key => $defaultValue) {
            if (array_key_exists($key, $features)) {
                $result[$key] = (bool) $features[$key];
            } else {
                $result[$key] = $defaultValue;
            }
        }

        // Check broker control config for exchange workflow
        $result['exchange_workflow'] = BrokerControlConfigService::isExchangeWorkflowEnabled();

        // Check if direct messaging is enabled (separate from exchange workflow)
        $result['direct_messaging'] = BrokerControlConfigService::isDirectMessagingEnabled();

        return $result;
    }

    /**
     * Build module configuration (core modules that can be disabled per tenant)
     *
     * Modules differ from features: modules are core platform functionality
     * (listings, wallet, messages) while features are optional add-ons
     * (gamification, goals, federation).
     *
     * Module config is read from tenants.configuration JSON → "modules" key.
     */
    private function buildModulesData(?array $config): array
    {
        $defaults = [
            'feed' => true,
            'listings' => true,
            'messages' => true,
            'wallet' => true,
            'notifications' => true,
            'profile' => true,
            'settings' => true,
            'dashboard' => true,
        ];

        $modules = [];
        if ($config !== null && isset($config['modules']) && is_array($config['modules'])) {
            $modules = $config['modules'];
        }

        $result = [];
        foreach ($defaults as $key => $defaultValue) {
            $result[$key] = array_key_exists($key, $modules) ? (bool) $modules[$key] : $defaultValue;
        }

        return $result;
    }

    /**
     * Build SEO metadata
     */
    private function buildSeoData(array $tenant): array
    {
        $seo = [];

        if (!empty($tenant['meta_title'])) {
            $seo['meta_title'] = $tenant['meta_title'];
        } elseif (!empty($tenant['name'])) {
            // Fallback to tenant name
            $seo['meta_title'] = $tenant['name'];
        }

        if (!empty($tenant['meta_description'])) {
            $seo['meta_description'] = $tenant['meta_description'];
        }

        if (!empty($tenant['h1_headline'])) {
            $seo['h1_headline'] = $tenant['h1_headline'];
        }

        if (!empty($tenant['hero_intro'])) {
            $seo['hero_intro'] = $tenant['hero_intro'];
        }

        return $seo;
    }

    /**
     * Build public configuration (footer text, etc.)
     *
     * IMPORTANT: Only include configuration that is safe for public display
     */
    private function buildPublicConfig(?array $config): array
    {
        if ($config === null) {
            return [];
        }

        $publicConfig = [];

        // Footer text (charity registration, etc.)
        if (!empty($config['footer_text'])) {
            $publicConfig['footer_text'] = $config['footer_text'];
        }

        // Module-specific configuration (public only)
        if (!empty($config['modules']) && is_array($config['modules'])) {
            $publicConfig['modules'] = $config['modules'];
        }

        // Currency/time unit display
        if (!empty($config['time_unit'])) {
            $publicConfig['time_unit'] = $config['time_unit'];
        }

        if (!empty($config['time_unit_plural'])) {
            $publicConfig['time_unit_plural'] = $config['time_unit_plural'];
        }

        return $publicConfig;
    }

    /**
     * Build contact information
     *
     * Only includes contact info that is meant to be public
     */
    private function buildContactData(array $tenant): array
    {
        $contact = [];

        // Public contact email (not admin email)
        if (!empty($tenant['contact_email'])) {
            $contact['email'] = $tenant['contact_email'];
        }

        if (!empty($tenant['contact_phone'])) {
            $contact['phone'] = $tenant['contact_phone'];
        }

        if (!empty($tenant['address'])) {
            $contact['address'] = $tenant['address'];
        }

        if (!empty($tenant['location_name'])) {
            $contact['location'] = $tenant['location_name'];
        }

        return $contact;
    }

    /**
     * Build social media links
     */
    private function buildSocialData(array $tenant): array
    {
        $social = [];

        $socialFields = [
            'social_facebook' => 'facebook',
            'social_twitter' => 'twitter',
            'social_instagram' => 'instagram',
            'social_linkedin' => 'linkedin',
            'social_youtube' => 'youtube',
        ];

        foreach ($socialFields as $field => $key) {
            if (!empty($tenant[$field])) {
                $social[$key] = $tenant[$field];
            }
        }

        return $social;
    }

    /**
     * GET /api/v2/tenants
     *
     * Returns list of all active tenants for login selector.
     * This is a PUBLIC endpoint - only returns minimal public info.
     *
     * Response: 200 OK with array of tenants
     */
    public function list(): void
    {
        // ?include_master=1 allows tenant 1 to appear (needed for super admin login)
        $includeMaster = !empty($_GET['include_master']);

        // Cache key varies by whether master is included
        $cacheKey = $includeMaster ? 'tenants_list_public_all' : 'tenants_list_public';
        $cached = RedisCache::get($cacheKey);

        if ($cached !== null) {
            $this->respondWithData($cached);
            return;
        }

        // Query active tenants; exclude master tenant (ID 1) by default
        $db = \Nexus\Core\Database::getConnection();
        $where = $includeMaster ? 'WHERE is_active = 1' : 'WHERE is_active = 1 AND id > 1';
        $stmt = $db->query("
            SELECT id, name, slug, domain, tagline
            FROM tenants
            {$where}
            ORDER BY id ASC
        ");
        $tenants = $stmt->fetchAll();

        // Build minimal public response
        $data = [];
        foreach ($tenants as $tenant) {
            $item = [
                'id' => (int) $tenant['id'],
                'name' => $tenant['name'],
                'slug' => $tenant['slug'],
            ];

            if (!empty($tenant['domain'])) {
                $item['domain'] = $tenant['domain'];
            }
            if (!empty($tenant['tagline'])) {
                $item['tagline'] = $tenant['tagline'];
            }

            $data[] = $item;
        }

        // Cache for 5 minutes
        RedisCache::set($cacheKey, $data, 300);

        $this->respondWithData($data);
    }

    /**
     * GET /api/v2/platform/stats
     *
     * Returns platform-wide statistics (across all tenants).
     * This is a PUBLIC endpoint for the landing page.
     *
     * Response: 200 OK with platform stats
     */
    public function platformStats(): void
    {
        // Try to get from cache first (cache for 5 minutes)
        $cacheKey = 'platform_stats_public';
        $cached = RedisCache::get($cacheKey);

        if ($cached !== null) {
            $this->respondWithData($cached);
            return;
        }

        $db = \Nexus\Core\Database::getConnection();

        // Active members (across all tenants)
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
        $activeMembers = (int) $stmt->fetchColumn();

        // Total hours exchanged (completed transactions)
        $stmt = $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed'");
        $hoursExchanged = (int) $stmt->fetchColumn();

        // Active listings
        $stmt = $db->query("SELECT COUNT(*) FROM listings WHERE status = 'active'");
        $activeListings = (int) $stmt->fetchColumn();

        // Distinct skills/categories
        $stmt = $db->query("SELECT COUNT(DISTINCT category_id) FROM listings WHERE status = 'active' AND category_id IS NOT NULL");
        $skillsListed = (int) $stmt->fetchColumn();

        // Active communities (tenants)
        $stmt = $db->query("SELECT COUNT(*) FROM tenants WHERE is_active = 1 AND id > 1");
        $communities = (int) $stmt->fetchColumn();

        $data = [
            'members' => $activeMembers,
            'hours_exchanged' => $hoursExchanged,
            'listings' => $activeListings,
            'skills' => $skillsListed,
            'communities' => $communities,
        ];

        // Cache for 5 minutes
        RedisCache::set($cacheKey, $data, 300);

        $this->respondWithData($data);
    }

    /**
     * Safely parse JSON, returning null on failure
     */
    private function parseJson($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
