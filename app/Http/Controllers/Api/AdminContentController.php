<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminContentController -- Content moderation (reports, approve, reject).
 *
 * All methods require admin authentication.
 */
class AdminContentController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/admin/content/reports */
    public function reports(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;

        $items = DB::select(
            'SELECT * FROM content_reports WHERE tenant_id = ? AND status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$tenantId, 'pending', $perPage, $offset]
        );
        $total = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM content_reports WHERE tenant_id = ? AND status = ?',
            [$tenantId, 'pending']
        )->cnt;

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /** POST /api/v2/admin/content/{id}/approve */
    public function approveContent(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $affected = DB::update(
            'UPDATE content_reports SET status = ?, resolved_at = NOW() WHERE id = ? AND tenant_id = ?',
            ['approved', $id, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Report not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'status' => 'approved']);
    }

    /** POST /api/v2/admin/content/{id}/reject */
    public function rejectContent(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $reason = $this->input('reason', '');

        $affected = DB::update(
            'UPDATE content_reports SET status = ?, rejection_reason = ?, resolved_at = NOW() WHERE id = ? AND tenant_id = ?',
            ['rejected', $reason, $id, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Report not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'status' => 'rejected']);
    }

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


    public function getPages(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'getPages');
    }


    public function createPage(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'createPage');
    }


    public function getPage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'getPage', [$id]);
    }


    public function updatePage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'updatePage', [$id]);
    }


    public function deletePage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'deletePage', [$id]);
    }


    public function getMenus(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'getMenus');
    }


    public function createMenu(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'createMenu');
    }


    public function getMenu($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'getMenu', [$id]);
    }


    public function updateMenu($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'updateMenu', [$id]);
    }


    public function deleteMenu($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'deleteMenu', [$id]);
    }


    public function getMenuItems($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'getMenuItems', [$id]);
    }


    public function createMenuItem($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'createMenuItem', [$id]);
    }


    public function reorderMenuItems($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'reorderMenuItems', [$id]);
    }


    public function updateMenuItem($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'updateMenuItem', [$id]);
    }


    public function deleteMenuItem($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'deleteMenuItem', [$id]);
    }


    public function getPlans(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'getPlans');
    }


    public function createPlan(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'createPlan');
    }


    public function getPlan($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'getPlan', [$id]);
    }


    public function updatePlan($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'updatePlan', [$id]);
    }


    public function deletePlan($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'deletePlan', [$id]);
    }


    public function getSubscriptions(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminContentApiController::class, 'getSubscriptions');
    }

}
