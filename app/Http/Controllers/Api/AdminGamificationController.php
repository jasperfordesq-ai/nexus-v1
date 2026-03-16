<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminGamificationController -- Admin gamification: badges, campaigns, bulk awards.
 *
 * Delegates to legacy controller during migration.
 */
class AdminGamificationController extends BaseApiController
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

    /** GET /api/v2/admin/gamification/stats */
    public function stats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGamificationApiController::class, 'stats');
    }

    /** GET /api/v2/admin/gamification/badges */
    public function badges(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGamificationApiController::class, 'badges');
    }

    /** POST /api/v2/admin/gamification/badges */
    public function createBadge(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGamificationApiController::class, 'createBadge');
    }

    /** DELETE /api/v2/admin/gamification/badges/{id} */
    public function deleteBadge(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGamificationApiController::class, 'deleteBadge', [$id]);
    }

    /** GET /api/v2/admin/gamification/campaigns */
    public function campaigns(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGamificationApiController::class, 'campaigns');
    }

    /** POST /api/v2/admin/gamification/campaigns */
    public function createCampaign(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGamificationApiController::class, 'createCampaign');
    }

    /** PUT /api/v2/admin/gamification/campaigns/{id} */
    public function updateCampaign(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGamificationApiController::class, 'updateCampaign', [$id]);
    }

    /** DELETE /api/v2/admin/gamification/campaigns/{id} */
    public function deleteCampaign(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGamificationApiController::class, 'deleteCampaign', [$id]);
    }

    /** POST /api/v2/admin/gamification/recheck-all */
    public function recheckAll(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGamificationApiController::class, 'recheckAll');
    }

    /** POST /api/v2/admin/gamification/bulk-award */
    public function bulkAward(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGamificationApiController::class, 'bulkAward');
    }
}
