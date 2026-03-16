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
}
