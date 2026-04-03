<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\GroupConfigurationService;
use App\Services\JobConfigurationService;
use App\Services\ListingConfigurationService;
use App\Services\RedisCache;
use App\Services\VolunteeringConfigurationService;
use App\Services\TenantFeatureConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Helpers\UrlHelper;
use App\Services\BrokerControlConfigService;

/**
 * TenantBootstrapController -- Tenant configuration bootstrap for SPA init.
 *
 * Native DB facade implementation (no delegation).
 *
 * Endpoints:
 *   GET /api/v2/tenant/bootstrap    bootstrap()
 *   GET /api/v2/tenants             list()
 *   GET /api/v2/platform/stats      platformStats()
 */
class TenantBootstrapController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly RedisCache $redisCache,
        private readonly TenantFeatureConfig $tenantFeatureConfig,
        private readonly BrokerControlConfigService $brokerControlConfigService,
    ) {}

    /** Cache TTL for tenant bootstrap data (10 minutes) */
    private const CACHE_TTL = 600;

    /** Cache key prefix for bootstrap data */
    private const CACHE_KEY = 'tenant_bootstrap';

    /**
     * GET /api/v2/tenant/bootstrap
     *
     * Returns tenant configuration for frontend initialization.
     * Public endpoint — no authentication required.
     *
     * Query Parameters:
     *   slug: (optional) - Tenant slug for explicit lookup (takes priority)
     */
    public function bootstrap(): JsonResponse
    {
        // Check for explicit slug query parameter (used by React frontend for tenant switching)
        $slug = $this->query('slug');
        if ($slug !== null && $slug !== '') {
            $slug = trim($slug);

            $slugTenant = DB::table('tenants')
                ->where('slug', $slug)
                ->where('is_active', 1)
                ->first();

            if (!$slugTenant) {
                // TRS-001 fail-closed: unknown slug MUST 404, never fall back to master tenant
                return $this->respondWithError(
                    'TENANT_NOT_FOUND',
                    __('api.community_not_found_or_inactive'),
                    null,
                    404
                );
            }

            $slugTenant = (array) $slugTenant;
            $tenantId = (int) $slugTenant['id'];

            // Set TenantContext so downstream calls read config for the correct tenant
            TenantContext::setById($tenantId);

            // Try cache first
            $cached = $this->redisCache->get(self::CACHE_KEY, $tenantId);
            if ($cached !== null) {
                return $this->respondWithData($cached);
            }

            $data = $this->buildBootstrapData($slugTenant);
            $this->redisCache->set(self::CACHE_KEY, $data, self::CACHE_TTL, $tenantId);
            return $this->respondWithData($data);
        }

        // Origin-based resolution for custom domains
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            if ($originHost) {
                $originHost = preg_replace('/^www\./', '', $originHost);

                $originTenant = DB::table('tenants')
                    ->where('domain', $originHost)
                    ->where('is_active', 1)
                    ->first();

                if ($originTenant && (int) $originTenant->id !== 1) {
                    $originTenant = (array) $originTenant;
                    $tenantId = (int) $originTenant['id'];

                    TenantContext::setById($tenantId);

                    $cached = $this->redisCache->get(self::CACHE_KEY, $tenantId);
                    if ($cached !== null) {
                        return $this->respondWithData($cached);
                    }

                    $data = $this->buildBootstrapData($originTenant);
                    $this->redisCache->set(self::CACHE_KEY, $data, self::CACHE_TTL, $tenantId);
                    return $this->respondWithData($data);
                }
            }
        }

        // Default: use TenantContext resolved by index.php
        $tenantId = TenantContext::getId();

        $cached = $this->redisCache->get(self::CACHE_KEY, $tenantId);
        if ($cached !== null) {
            return $this->respondWithData($cached);
        }

        $tenant = TenantContext::get();

        // Guard: if resolved tenant is inactive, reject
        if (!empty($tenant['id']) && $tenant['id'] > 1 && empty($tenant['is_active'])) {
            return $this->respondWithError(
                \App\Core\ApiErrorCodes::INVALID_TENANT,
                __('api.tenant_inactive'),
                null,
                503
            );
        }

        $data = $this->buildBootstrapData($tenant);
        $this->redisCache->set(self::CACHE_KEY, $data, self::CACHE_TTL, $tenantId);

        return $this->respondWithData($data);
    }

    /**
     * GET /api/v2/tenants
     *
     * Returns list of all active tenants for login selector.
     * Public endpoint — only returns minimal public info.
     */
    public function list(): JsonResponse
    {
        $includeMaster = $this->queryBool('include_master');

        $cacheKey = $includeMaster ? 'tenants_list_public_all' : 'tenants_list_public';
        $cached = $this->redisCache->get($cacheKey);

        if ($cached !== null) {
            return $this->respondWithData($cached);
        }

        $query = DB::table('tenants')
            ->select('id', 'name', 'slug', 'domain', 'tagline')
            ->where('is_active', 1);

        if (!$includeMaster) {
            $query->where('id', '>', 1);
        }

        $tenants = $query->orderBy('id', 'asc')->get();

        $data = [];
        foreach ($tenants as $tenant) {
            $item = [
                'id' => (int) $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ];

            if (!empty($tenant->domain)) {
                $item['domain'] = $tenant->domain;
            }
            if (!empty($tenant->tagline)) {
                $item['tagline'] = $tenant->tagline;
            }

            $data[] = $item;
        }

        $this->redisCache->set($cacheKey, $data, 300);

        return $this->respondWithData($data);
    }

    /**
     * GET /api/v2/platform/stats
     *
     * Returns platform-wide statistics (across all tenants).
     * Public endpoint for the landing page.
     */
    public function platformStats(): JsonResponse
    {
        $cacheKey = 'platform_stats_public';
        $cached = $this->redisCache->get($cacheKey);

        if ($cached !== null) {
            return $this->respondWithData($cached);
        }

        $activeMembers = (int) DB::table('users')
            ->where('status', 'active')
            ->count();

        $hoursExchanged = (int) DB::table('transactions')
            ->where('status', 'completed')
            ->sum('amount');

        $activeListings = (int) DB::table('listings')
            ->where('status', 'active')
            ->count();

        $skillsListed = (int) DB::table('listings')
            ->where('status', 'active')
            ->whereNotNull('category_id')
            ->distinct('category_id')
            ->count('category_id');

        $communities = (int) DB::table('tenants')
            ->where('is_active', 1)
            ->where('id', '>', 1)
            ->count();

        $data = [
            'members' => $activeMembers,
            'hours_exchanged' => $hoursExchanged,
            'listings' => $activeListings,
            'skills' => $skillsListed,
            'communities' => $communities,
        ];

        $this->redisCache->set($cacheKey, $data, 300);

        return $this->respondWithData($data);
    }

    // ========================================================================
    // Private helpers — ported from legacy TenantBootstrapController
    // ========================================================================

    /**
     * Build bootstrap data from tenant record.
     * Only exposes PUBLIC configuration.
     */
    private function buildBootstrapData(array $tenant): array
    {
        $config = $this->parseJson($tenant['configuration'] ?? null);
        $features = $this->parseJson($tenant['features'] ?? null);

        $data = [
            'id' => (int) $tenant['id'],
            'name' => $tenant['name'] ?? '',
            'slug' => $tenant['slug'] ?? '',
        ];

        if (!empty($tenant['domain'])) {
            $data['domain'] = $tenant['domain'];
        }
        if (!empty($tenant['tagline'])) {
            $data['tagline'] = $tenant['tagline'];
        }

        $data['default_layout'] = $tenant['default_layout'] ?? 'modern';
        $data['branding'] = $this->buildBrandingData($tenant, $config);
        $data['features'] = $this->buildFeaturesData($features);
        $data['modules'] = $this->buildModulesData($config);
        $data['seo'] = $this->buildSeoData($tenant);

        $publicConfig = $this->buildPublicConfig($config, (int) $tenant['id']);
        if (!empty($publicConfig)) {
            $data['config'] = $publicConfig;
        }

        $contact = $this->buildContactData($tenant);
        if (!empty($contact)) {
            $data['contact'] = $contact;
        }

        $social = $this->buildSocialData($tenant);
        if (!empty($social)) {
            $data['social'] = $social;
        }

        $data['settings'] = $this->buildGeneralSettings((int) $tenant['id']);

        // Add onboarding module flags to bootstrap (non-sensitive, safe for public payload)
        $data['settings']['onboarding_enabled'] = $this->getOnboardingSetting((int) $tenant['id'], 'onboarding.enabled', '1') === '1';
        $data['settings']['onboarding_mandatory'] = $this->getOnboardingSetting((int) $tenant['id'], 'onboarding.mandatory', '1') === '1';

        $data['compliance'] = [
            'vetting_enabled' => $this->brokerControlConfigService->isVettingEnabled(),
            'insurance_enabled' => $this->brokerControlConfigService->isInsuranceEnabled(),
        ];

        // Listing module configuration — expose to frontend for UI gating
        $data['listing_config'] = ListingConfigurationService::getAll();

        // Volunteering module configuration — tabs + feature options
        $data['volunteering_config'] = VolunteeringConfigurationService::getAll();

        // Jobs module configuration
        $data['job_config'] = JobConfigurationService::getAll();

        // Group tab visibility — only include tab_* keys for frontend filtering
        $groupConfig = GroupConfigurationService::getAll();
        $groupTabs = [];
        foreach ($groupConfig as $key => $value) {
            if (str_starts_with($key, 'tab_')) {
                $groupTabs[$key] = (bool) $value;
            }
        }
        $data['group_tabs'] = $groupTabs;

        $data['supported_languages'] = $config['supported_languages'] ?? ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'];
        $data['default_language'] = $config['default_language'] ?? 'en';

        $menuPages = $this->buildMenuPages((int) $tenant['id']);
        if (!empty($menuPages)) {
            $data['menu_pages'] = $menuPages;
        }

        return $data;
    }

    /**
     * Public-safe setting keys that may be exposed in the bootstrap payload.
     * Admin-only settings (maintenance_mode, admin_approval, registration_mode,
     * email_verification, max_upload_size_mb, etc.) are deliberately excluded.
     */
    private const PUBLIC_GENERAL_SETTINGS = [
        'timezone', 'default_currency', 'date_format', 'time_format',
        'items_per_page', 'welcome_credits', 'footer_text', 'welcome_message',
        'seo_google_verification', 'seo_bing_verification',
    ];

    private function buildGeneralSettings(int $tenantId): array
    {
        $settings = [];

        try {
            $rows = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'LIKE', 'general.%')
                ->select('setting_key', 'setting_value')
                ->get();

            foreach ($rows as $row) {
                $key = str_replace('general.', '', $row->setting_key);

                // Only expose whitelisted public settings — never admin-only keys
                if (!in_array($key, self::PUBLIC_GENERAL_SETTINGS, true)) {
                    continue;
                }

                $value = $row->setting_value;

                if ($value === 'true' || $value === '1') {
                    $settings[$key] = true;
                } elseif ($value === 'false' || $value === '0') {
                    $settings[$key] = false;
                } else {
                    $settings[$key] = $value;
                }
            }
        } catch (\Exception $e) {
            // tenant_settings table may not exist yet
        }

        return $settings;
    }

    /**
     * Read a single onboarding setting with a default fallback.
     */
    private function getOnboardingSetting(int $tenantId, string $key, string $default): string
    {
        try {
            $row = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', $key)
                ->value('setting_value');
            return $row !== null ? (string) $row : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function buildMenuPages(int $tenantId): array
    {
        $result = [];

        try {
            $rows = DB::table('pages')
                ->where('tenant_id', $tenantId)
                ->where('is_published', 1)
                ->where('show_in_menu', 1)
                ->select('title', 'slug', 'menu_location')
                ->orderBy('menu_order')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get();

            foreach ($rows as $row) {
                $location = $row->menu_location ?: 'about';
                $result[$location][] = [
                    'title' => $row->title,
                    'slug' => $row->slug,
                ];
            }
        } catch (\Exception $e) {
            // pages table may not exist yet
        }

        return $result;
    }

    private function buildBrandingData(array $tenant, ?array $config): array
    {
        $branding = [];

        if (!empty($tenant['logo_url'])) {
            $branding['logo_url'] = UrlHelper::absolute($tenant['logo_url']);
        } elseif (!empty($config['logo_url'])) {
            $branding['logo_url'] = UrlHelper::absolute($config['logo_url']);
        }

        if (!empty($tenant['favicon_url'])) {
            $branding['favicon_url'] = UrlHelper::absolute($tenant['favicon_url']);
        } elseif (!empty($config['favicon_url'])) {
            $branding['favicon_url'] = UrlHelper::absolute($config['favicon_url']);
        }

        if (!empty($tenant['primary_color'])) {
            $branding['primary_color'] = $tenant['primary_color'];
        } elseif (!empty($config['primary_color'])) {
            $branding['primary_color'] = $config['primary_color'];
        }

        if (!empty($tenant['og_image_url'])) {
            $branding['og_image_url'] = UrlHelper::absolute($tenant['og_image_url']);
        }

        return $branding;
    }

    private function buildFeaturesData(?array $features): array
    {
        return $this->tenantFeatureConfig->mergeFeatures($features);
    }

    private function buildModulesData(?array $config): array
    {
        $modules = null;
        if ($config !== null && isset($config['modules']) && is_array($config['modules'])) {
            $modules = $config['modules'];
        }

        return $this->tenantFeatureConfig->mergeModules($modules);
    }

    private function buildSeoData(array $tenant): array
    {
        $seo = [];

        if (!empty($tenant['meta_title'])) {
            $seo['meta_title'] = $tenant['meta_title'];
        } elseif (!empty($tenant['name'])) {
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

    private function buildPublicConfig(?array $config, int $tenantId = 0): array
    {
        $publicConfig = [];

        $footerText = '';
        if ($tenantId > 0) {
            try {
                $row = DB::table('tenant_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('setting_key', 'general.footer_text')
                    ->select('setting_value')
                    ->first();

                if ($row && !empty($row->setting_value)) {
                    $footerText = $row->setting_value;
                }
            } catch (\Exception $e) {
                // tenant_settings table may not exist yet
            }
        }

        if (empty($footerText) && $config !== null && !empty($config['footer_text'])) {
            $footerText = $config['footer_text'];
        }
        if (!empty($footerText)) {
            $publicConfig['footer_text'] = $footerText;
        }

        if ($config === null) {
            return $publicConfig;
        }

        if (!empty($config['modules']) && is_array($config['modules'])) {
            $publicConfig['modules'] = $config['modules'];
        }

        if (!empty($config['time_unit'])) {
            $publicConfig['time_unit'] = $config['time_unit'];
        }

        if (!empty($config['time_unit_plural'])) {
            $publicConfig['time_unit_plural'] = $config['time_unit_plural'];
        }

        return $publicConfig;
    }

    private function buildContactData(array $tenant): array
    {
        $contact = [];

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
     * Safely parse JSON, returning null on failure.
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
