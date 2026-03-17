<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\RedisCache;
use Nexus\Services\TenantFeatureConfig;
use Nexus\Services\FederationFeatureService;

/**
 * AdminConfigController -- Admin system configuration, features, modules, cache, jobs,
 * cron, AI, feed algorithm, SEO, image, language, native app, algorithm info.
 *
 * All methods require admin authentication.
 */
class AdminConfigController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    // =========================================================================
    // Config
    // =========================================================================

    /** GET /api/v2/admin/config */
    public function getConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $tenant = DB::selectOne("SELECT features, configuration FROM tenants WHERE id = ?", [$tenantId]);

        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        if ($tenant && !empty($tenant->features)) {
            $dbFeatures = json_decode($tenant->features, true) ?: [];
            foreach ($dbFeatures as $key => $value) {
                if (array_key_exists($key, $features)) {
                    $features[$key] = (bool) $value;
                }
            }
        }

        $modules = TenantFeatureConfig::MODULE_DEFAULTS;
        if ($tenant && !empty($tenant->configuration)) {
            $config = json_decode($tenant->configuration, true) ?: [];
            $dbModules = $config['modules'] ?? [];
            foreach ($dbModules as $key => $value) {
                if (array_key_exists($key, $modules)) {
                    $modules[$key] = (bool) $value;
                }
            }
        }

        return $this->respondWithData([
            'tenant_id' => $tenantId,
            'features' => $features,
            'modules' => $modules,
        ]);
    }

    /** PUT /api/v2/admin/config/features */
    public function updateFeature(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $featureName = $this->input('feature');
        $enabled = $this->input('enabled');

        if (!$featureName || !is_string($featureName)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Feature name is required', 'feature', 422);
        }
        if (!array_key_exists($featureName, TenantFeatureConfig::FEATURE_DEFAULTS)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Unknown feature: ' . $featureName, 'feature', 422);
        }
        if ($enabled === null) {
            return $this->respondWithError('VALIDATION_ERROR', 'Enabled value is required', 'enabled', 422);
        }

        $tenant = DB::selectOne("SELECT features FROM tenants WHERE id = ?", [$tenantId]);
        $features = ($tenant && !empty($tenant->features)) ? (json_decode($tenant->features, true) ?: []) : [];
        $features[$featureName] = (bool) $enabled;

        DB::update("UPDATE tenants SET features = ? WHERE id = ?", [json_encode($features), $tenantId]);

        if ($featureName === 'federation') {
            if ((bool) $enabled) {
                FederationFeatureService::enableTenantFeature(FederationFeatureService::TENANT_FEDERATION_ENABLED, $tenantId);
            } else {
                FederationFeatureService::disableTenantFeature(FederationFeatureService::TENANT_FEDERATION_ENABLED, $tenantId);
            }
        }

        RedisCache::delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['feature' => $featureName, 'enabled' => (bool) $enabled]);
    }

    /** PUT /api/v2/admin/config/modules */
    public function updateModule(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $moduleName = $this->input('module');
        $enabled = $this->input('enabled');

        if (!$moduleName || !is_string($moduleName)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Module name is required', 'module', 422);
        }
        if (!array_key_exists($moduleName, TenantFeatureConfig::MODULE_DEFAULTS)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Unknown module: ' . $moduleName, 'module', 422);
        }
        if ($enabled === null) {
            return $this->respondWithError('VALIDATION_ERROR', 'Enabled value is required', 'enabled', 422);
        }

        $tenant = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
        $config = ($tenant && !empty($tenant->configuration)) ? (json_decode($tenant->configuration, true) ?: []) : [];
        if (!isset($config['modules'])) {
            $config['modules'] = TenantFeatureConfig::MODULE_DEFAULTS;
        }
        $config['modules'][$moduleName] = (bool) $enabled;

        DB::update("UPDATE tenants SET configuration = ? WHERE id = ?", [json_encode($config), $tenantId]);
        RedisCache::delete('tenant_bootstrap', $tenantId);

        return $this->respondWithData(['module' => $moduleName, 'enabled' => (bool) $enabled]);
    }

    // =========================================================================
    // Cache
    // =========================================================================

    /** GET /api/v2/admin/config/cache-stats */
    public function cacheStats(): JsonResponse
    {
        $this->requireAdmin();
        $stats = RedisCache::getStats();

        return $this->respondWithData([
            'redis_connected' => $stats['enabled'] ?? false,
            'redis_memory_used' => $stats['memory_used'] ?? '0B',
            'redis_keys_count' => $stats['total_keys'] ?? 0,
            'cache_hit_rate' => 0.0,
        ]);
    }

    /** POST /api/v2/admin/config/clear-cache */
    public function clearCache(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $type = $this->input('type', 'tenant');

        try {
            if ($type === 'all') {
                foreach ([1, 2, 3, 4, 5] as $tid) {
                    RedisCache::clearTenant($tid);
                }
            } else {
                RedisCache::clearTenant($tenantId);
            }
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to clear cache', null, 500);
        }

        return $this->respondWithData(['cleared' => true, 'type' => $type]);
    }

    // =========================================================================
    // Jobs
    // =========================================================================

    /** GET /api/v2/admin/config/jobs */
    public function getJobs(): JsonResponse
    {
        $this->requireAdmin();

        $jobs = [
            ['id' => 'digest_emails', 'name' => 'Email Digest Sender', 'status' => 'idle', 'last_run_at' => null, 'next_run_at' => null],
            ['id' => 'badge_checker', 'name' => 'Badge Award Checker', 'status' => 'idle', 'last_run_at' => null, 'next_run_at' => null],
            ['id' => 'streak_updater', 'name' => 'Login Streak Updater', 'status' => 'idle', 'last_run_at' => null, 'next_run_at' => null],
        ];

        return $this->respondWithData($jobs);
    }

    /** POST /api/v2/admin/config/jobs/run */
    public function runJob(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData(['triggered' => true]);
    }

    // =========================================================================
    // Cron Jobs & Settings (delegate — complex script-execution logic)
    // =========================================================================

    /** GET /api/v2/admin/config/cron-jobs */
    public function getCronJobs(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getCronJobs');
    }

    /** POST /api/v2/admin/config/cron-jobs/run */
    public function runCronJob(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'runCronJob');
    }

    // =========================================================================
    // Settings
    // =========================================================================

    /** GET /api/v2/admin/config/settings */
    public function getSettings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getSettings');
    }

    /** PUT /api/v2/admin/config/settings */
    public function updateSettings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateSettings');
    }

    // =========================================================================
    // AI Config
    // =========================================================================

    /** GET /api/v2/admin/config/ai */
    public function getAiConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getAiConfig');
    }

    /** PUT /api/v2/admin/config/ai */
    public function updateAiConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateAiConfig');
    }

    // =========================================================================
    // Feed Algorithm
    // =========================================================================

    /** GET /api/v2/admin/config/feed-algorithm */
    public function getFeedAlgorithmConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getFeedAlgorithmConfig');
    }

    /** PUT /api/v2/admin/config/feed-algorithm */
    public function updateFeedAlgorithmConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateFeedAlgorithmConfig');
    }

    // =========================================================================
    // Image Config
    // =========================================================================

    /** GET /api/v2/admin/config/image */
    public function getImageConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getImageConfig');
    }

    /** PUT /api/v2/admin/config/image */
    public function updateImageConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateImageConfig');
    }

    // =========================================================================
    // SEO Config
    // =========================================================================

    /** GET /api/v2/admin/config/seo */
    public function getSeoConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getSeoConfig');
    }

    /** PUT /api/v2/admin/config/seo */
    public function updateSeoConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateSeoConfig');
    }

    // =========================================================================
    // Native App / Language / Algorithm
    // =========================================================================

    /** GET /api/v2/admin/config/native-app */
    public function getNativeAppConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getNativeAppConfig');
    }

    /** PUT /api/v2/admin/config/native-app */
    public function updateNativeAppConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateNativeAppConfig');
    }

    /** GET /api/v2/admin/config/algorithm-info */
    public function getAlgorithmInfo(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getAlgorithmInfo');
    }

    /** GET /api/v2/admin/config/algorithm */
    public function getAlgorithmConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getAlgorithmConfig');
    }

    /** PUT /api/v2/admin/config/algorithm/{area} */
    public function updateAlgorithmConfig($area): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateAlgorithmConfig', [$area]);
    }

    /** GET /api/v2/admin/config/algorithm-health */
    public function getAlgorithmHealth(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getAlgorithmHealth');
    }

    /** GET /api/v2/admin/config/language */
    public function getLanguageConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getLanguageConfig');
    }

    /** PUT /api/v2/admin/config/language */
    public function updateLanguageConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateLanguageConfig');
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
