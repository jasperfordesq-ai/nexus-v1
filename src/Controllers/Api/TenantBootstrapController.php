<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\RedisCache;
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
     * Response: 200 OK with tenant data
     */
    public function bootstrap(): void
    {
        // TenantContext already resolved by index.php
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

        // Feature flags (what modules are enabled)
        $data['features'] = $this->buildFeaturesData($features);

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

        return $data;
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
        ];

        if ($features === null) {
            return $defaults;
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
        // Try to get from cache first
        $cacheKey = 'tenants_list_public';
        $cached = RedisCache::get($cacheKey);

        if ($cached !== null) {
            $this->respondWithData($cached);
            return;
        }

        // Query all active tenants (excluding master/admin tenant ID 1)
        $db = \Nexus\Core\Database::getConnection();
        $stmt = $db->query("
            SELECT id, name, slug, domain, tagline
            FROM tenants
            WHERE is_active = 1 AND id > 1
            ORDER BY name ASC
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

            // Optional fields
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
