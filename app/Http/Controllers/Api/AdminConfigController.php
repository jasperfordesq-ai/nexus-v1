<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminConfigController -- Admin system configuration, features, modules, cron, AI, SEO.
 *
 * Delegates to legacy controller during migration.
 */
class AdminConfigController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    /** GET /api/v2/admin/config */
    public function getConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getConfig');
    }

    /** PUT /api/v2/admin/config/features */
    public function updateFeature(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateFeature');
    }

    /** PUT /api/v2/admin/config/modules */
    public function updateModule(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateModule');
    }

    /** GET /api/v2/admin/config/cache-stats */
    public function cacheStats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'cacheStats');
    }

    /** POST /api/v2/admin/config/clear-cache */
    public function clearCache(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'clearCache');
    }

    /** GET /api/v2/admin/config/jobs */
    public function getJobs(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getJobs');
    }

    /** POST /api/v2/admin/config/jobs/run */
    public function runJob(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'runJob');
    }

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

    /** GET /api/v2/admin/config/native-app */
    public function getNativeAppConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getNativeAppConfig');
    }

    public function getAlgorithmInfo(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getAlgorithmInfo');
    }


    public function getAlgorithmConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getAlgorithmConfig');
    }


    public function updateAlgorithmConfig($area): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateAlgorithmConfig', [$area]);
    }


    public function getAlgorithmHealth(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getAlgorithmHealth');
    }


    public function getLanguageConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'getLanguageConfig');
    }


    public function updateLanguageConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateLanguageConfig');
    }


    public function updateNativeAppConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminConfigApiController::class, 'updateNativeAppConfig');
    }

}
