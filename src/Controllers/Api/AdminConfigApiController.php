<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Services\RedisCache;

/**
 * AdminConfigApiController - V2 API for React admin system configuration
 *
 * Provides endpoints for managing tenant features, modules, cache, and jobs.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/config            - Get current config (features + modules)
 * - PUT    /api/v2/admin/config/features   - Toggle a feature
 * - PUT    /api/v2/admin/config/modules    - Toggle a module
 * - GET    /api/v2/admin/cache/stats       - Get Redis cache stats
 * - POST   /api/v2/admin/cache/clear       - Clear cache
 * - GET    /api/v2/admin/jobs              - Get background jobs status
 * - POST   /api/v2/admin/jobs/{id}/run     - Trigger a job manually
 */
class AdminConfigApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * All known features with defaults
     */
    private const FEATURE_DEFAULTS = [
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
        'direct_messaging' => true,
    ];

    /**
     * All known modules with defaults
     */
    private const MODULE_DEFAULTS = [
        'listings' => true,
        'wallet' => true,
        'messages' => true,
        'dashboard' => true,
        'feed' => true,
        'notifications' => true,
        'profile' => true,
        'settings' => true,
    ];

    /**
     * GET /api/v2/admin/config
     */
    public function getConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $tenant = Database::query(
            "SELECT features, configuration FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch();

        $features = self::FEATURE_DEFAULTS;
        if ($tenant && !empty($tenant['features'])) {
            $dbFeatures = json_decode($tenant['features'], true) ?: [];
            foreach ($dbFeatures as $key => $value) {
                if (array_key_exists($key, $features)) {
                    $features[$key] = (bool) $value;
                }
            }
        }

        $modules = self::MODULE_DEFAULTS;
        if ($tenant && !empty($tenant['configuration'])) {
            $config = json_decode($tenant['configuration'], true) ?: [];
            $dbModules = $config['modules'] ?? [];
            foreach ($dbModules as $key => $value) {
                if (array_key_exists($key, $modules)) {
                    $modules[$key] = (bool) $value;
                }
            }
        }

        $this->respondWithData([
            'tenant_id' => $tenantId,
            'features' => $features,
            'modules' => $modules,
        ]);
    }

    /**
     * PUT /api/v2/admin/config/features
     *
     * Body: { "feature": "gamification", "enabled": true }
     */
    public function updateFeature(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();
        $featureName = $input['feature'] ?? null;
        $enabled = $input['enabled'] ?? null;

        if (!$featureName || !is_string($featureName)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Feature name is required', 'feature', 422);
        }

        if (!array_key_exists($featureName, self::FEATURE_DEFAULTS)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Unknown feature: ' . $featureName, 'feature', 422);
        }

        if ($enabled === null) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Enabled value is required', 'enabled', 422);
        }

        // Read current features
        $tenant = Database::query(
            "SELECT features FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch();

        $features = [];
        if ($tenant && !empty($tenant['features'])) {
            $features = json_decode($tenant['features'], true) ?: [];
        }

        $features[$featureName] = (bool) $enabled;

        Database::query(
            "UPDATE tenants SET features = ? WHERE id = ?",
            [json_encode($features), $tenantId]
        );

        // Clear bootstrap cache
        RedisCache::delete('tenant_bootstrap', $tenantId);

        $this->respondWithData([
            'feature' => $featureName,
            'enabled' => (bool) $enabled,
        ]);
    }

    /**
     * PUT /api/v2/admin/config/modules
     *
     * Body: { "module": "wallet", "enabled": true }
     */
    public function updateModule(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();
        $moduleName = $input['module'] ?? null;
        $enabled = $input['enabled'] ?? null;

        if (!$moduleName || !is_string($moduleName)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Module name is required', 'module', 422);
        }

        if (!array_key_exists($moduleName, self::MODULE_DEFAULTS)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Unknown module: ' . $moduleName, 'module', 422);
        }

        if ($enabled === null) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Enabled value is required', 'enabled', 422);
        }

        // Read current configuration
        $tenant = Database::query(
            "SELECT configuration FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch();

        $config = [];
        if ($tenant && !empty($tenant['configuration'])) {
            $config = json_decode($tenant['configuration'], true) ?: [];
        }

        if (!isset($config['modules'])) {
            $config['modules'] = self::MODULE_DEFAULTS;
        }

        $config['modules'][$moduleName] = (bool) $enabled;

        Database::query(
            "UPDATE tenants SET configuration = ? WHERE id = ?",
            [json_encode($config), $tenantId]
        );

        // Clear bootstrap cache
        RedisCache::delete('tenant_bootstrap', $tenantId);

        $this->respondWithData([
            'module' => $moduleName,
            'enabled' => (bool) $enabled,
        ]);
    }

    /**
     * GET /api/v2/admin/cache/stats
     */
    public function cacheStats(): void
    {
        $this->requireAdmin();

        $stats = RedisCache::getStats();

        $this->respondWithData([
            'redis_connected' => $stats['enabled'] ?? false,
            'redis_memory_used' => $stats['memory_used'] ?? '0B',
            'redis_keys_count' => $stats['total_keys'] ?? 0,
            'cache_hit_rate' => 0.0,
        ]);
    }

    /**
     * POST /api/v2/admin/cache/clear
     *
     * Body: { "type": "all" | "tenant" | "routes" | "views" }
     */
    public function clearCache(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();
        $type = $input['type'] ?? 'tenant';

        try {
            if ($type === 'all') {
                // Clear all tenants
                foreach ([1, 2, 3, 4, 5] as $tid) {
                    RedisCache::clearTenant($tid);
                }
            } else {
                RedisCache::clearTenant($tenantId);
            }
        } catch (\Throwable $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to clear cache', null, 500);
        }

        $this->respondWithData(['cleared' => true, 'type' => $type]);
    }

    /**
     * GET /api/v2/admin/jobs
     */
    public function getJobs(): void
    {
        $this->requireAdmin();

        // Return known background jobs with their status
        $jobs = [
            [
                'id' => 'digest_emails',
                'name' => 'Email Digest Sender',
                'status' => 'idle',
                'last_run_at' => null,
                'next_run_at' => null,
            ],
            [
                'id' => 'badge_checker',
                'name' => 'Badge Award Checker',
                'status' => 'idle',
                'last_run_at' => null,
                'next_run_at' => null,
            ],
            [
                'id' => 'streak_updater',
                'name' => 'Login Streak Updater',
                'status' => 'idle',
                'last_run_at' => null,
                'next_run_at' => null,
            ],
        ];

        $this->respondWithData($jobs);
    }

    /**
     * POST /api/v2/admin/jobs/{id}/run
     */
    public function runJob(): void
    {
        $this->requireAdmin();

        // Placeholder — jobs would be triggered here
        $this->respondWithData(['triggered' => true]);
    }
}
