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
}
