<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminListingsController -- Admin listing moderation (pending, approve, reject, stats).
 *
 * All methods require admin authentication.
 */
class AdminListingsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/admin/listings/pending */
    public function pending(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);

        $offset = ($page - 1) * $perPage;
        $items = DB::select(
            'SELECT * FROM listings WHERE tenant_id = ? AND status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$tenantId, 'pending', $perPage, $offset]
        );
        $total = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ? AND status = ?',
            [$tenantId, 'pending']
        )->cnt;

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /** POST /api/v2/admin/listings/{id}/approve */
    public function approve(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $affected = DB::update(
            'UPDATE listings SET status = ? WHERE id = ? AND tenant_id = ?',
            ['active', $id, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'status' => 'active']);
    }

    /** POST /api/v2/admin/listings/{id}/reject */
    public function reject(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $reason = $this->input('reason', '');

        $affected = DB::update(
            'UPDATE listings SET status = ?, rejection_reason = ? WHERE id = ? AND tenant_id = ?',
            ['rejected', $reason, $id, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'status' => 'rejected']);
    }

    /** GET /api/v2/admin/listings/stats */
    public function stats(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $rows = DB::select(
            'SELECT status, COUNT(*) as count FROM listings WHERE tenant_id = ? GROUP BY status',
            [$tenantId]
        );

        $stats = [];
        foreach ($rows as $row) {
            $stats[$row->status] = (int) $row->count;
        }

        return $this->respondWithData($stats);
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


    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminListingsApiController::class, 'index');
    }


    public function moderationQueue(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminListingsApiController::class, 'moderationQueue');
    }


    public function moderationStats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminListingsApiController::class, 'moderationStats');
    }


    public function show($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminListingsApiController::class, 'show', [$id]);
    }


    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminListingsApiController::class, 'destroy', [$id]);
    }


    public function feature($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminListingsApiController::class, 'feature', [$id]);
    }


    public function unfeature($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminListingsApiController::class, 'unfeature', [$id]);
    }


    public function searchAnalytics(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminListingsApiController::class, 'searchAnalytics');
    }


    public function searchTrending(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminListingsApiController::class, 'searchTrending');
    }


    public function searchZeroResults(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminListingsApiController::class, 'searchZeroResults');
    }

}
