<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminToolsController -- Admin tools: redirects, 404s, health checks, WebP, SEO audit.
 *
 * Delegates to legacy controller during migration.
 */
class AdminToolsController extends BaseApiController
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

    /** GET /api/v2/admin/tools/redirects */
    public function getRedirects(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'getRedirects');
    }

    /** POST /api/v2/admin/tools/redirects */
    public function createRedirect(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'createRedirect');
    }

    /** DELETE /api/v2/admin/tools/redirects/{id} */
    public function deleteRedirect(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'deleteRedirect', [$id]);
    }

    /** GET /api/v2/admin/tools/404-errors */
    public function get404Errors(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'get404Errors');
    }

    /** DELETE /api/v2/admin/tools/404-errors/{id} */
    public function delete404Error(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'delete404Error', [$id]);
    }

    /** POST /api/v2/admin/tools/health-check */
    public function runHealthCheck(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'runHealthCheck');
    }

    /** GET /api/v2/admin/tools/webp-stats */
    public function getWebpStats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'getWebpStats');
    }

    /** POST /api/v2/admin/tools/webp-conversion */
    public function runWebpConversion(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'runWebpConversion');
    }

    /** POST /api/v2/admin/tools/seed-generator */
    public function runSeedGenerator(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'runSeedGenerator');
    }

    /** GET /api/v2/admin/tools/blog-backups */
    public function getBlogBackups(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'getBlogBackups');
    }

    /** POST /api/v2/admin/tools/blog-backups/restore */
    public function restoreBlogBackup(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'restoreBlogBackup');
    }

    /** GET /api/v2/admin/tools/seo-audit */
    public function getSeoAudit(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'getSeoAudit');
    }

    /** POST /api/v2/admin/tools/seo-audit */
    public function runSeoAudit(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'runSeoAudit');
    }

    /** GET /api/v2/admin/tools/ip-debug */
    public function ipDebug(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminToolsApiController::class, 'ipDebug');
    }
}
