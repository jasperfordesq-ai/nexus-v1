<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminGroupsController -- Admin group management (list, view, approve, delete).
 *
 * All methods require admin authentication.
 */
class AdminGroupsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/admin/groups */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;

        $items = DB::select(
            'SELECT * FROM `groups` WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$tenantId, $perPage, $offset]
        );
        $total = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM `groups` WHERE tenant_id = ?',
            [$tenantId]
        )->cnt;

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /** GET /api/v2/admin/groups/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $group = DB::selectOne(
            'SELECT * FROM `groups` WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($group === null) {
            return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        return $this->respondWithData($group);
    }

    /** POST /api/v2/admin/groups/{id}/approve */
    public function approve(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $affected = DB::update(
            'UPDATE `groups` SET status = ? WHERE id = ? AND tenant_id = ?',
            ['active', $id, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'status' => 'active']);
    }

    /** DELETE /api/v2/admin/groups/{id} */
    public function destroy(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $affected = DB::delete(
            'DELETE FROM `groups` WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'deleted' => true]);
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


    public function analytics(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'analytics');
    }


    public function approvals(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'approvals');
    }


    public function approveMember($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'approveMember', [$id]);
    }


    public function rejectMember($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'rejectMember', [$id]);
    }


    public function moderation(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'moderation');
    }


    public function getGroupTypes(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'getGroupTypes');
    }


    public function createGroupType(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'createGroupType');
    }


    public function updateGroupType($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'updateGroupType', [$id]);
    }


    public function deleteGroupType($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'deleteGroupType', [$id]);
    }


    public function getPolicies($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'getPolicies', [$id]);
    }


    public function setPolicy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'setPolicy', [$id]);
    }


    public function batchGeocode(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'batchGeocode');
    }


    public function getRecommendationData(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'getRecommendationData');
    }


    public function getFeaturedGroups(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'getFeaturedGroups');
    }


    public function updateFeaturedGroups(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'updateFeaturedGroups');
    }


    public function updateStatus($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'updateStatus', [$id]);
    }


    public function deleteGroup($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'deleteGroup', [$id]);
    }


    public function getGroup($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'getGroup', [$id]);
    }


    public function updateGroup($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'updateGroup', [$id]);
    }


    public function toggleFeatured($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'toggleFeatured', [$id]);
    }


    public function geocodeGroup($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'geocodeGroup', [$id]);
    }


    public function getMembers($groupId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'getMembers', [$groupId]);
    }


    public function promoteMember($groupId, $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'promoteMember', [$groupId, $userId]);
    }


    public function demoteMember($groupId, $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'demoteMember', [$groupId, $userId]);
    }


    public function kickMember($groupId, $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminGroupsApiController::class, 'kickMember', [$groupId, $userId]);
    }


    public function apiData($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\GroupAnalyticsController::class, 'apiData', [$id]);
    }

}
