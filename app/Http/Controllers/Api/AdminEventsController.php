<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Services\EventService;

/**
 * AdminEventsController -- Admin event management (list, view, approve, delete, cancel).
 *
 * All methods require admin authentication.
 */
class AdminEventsController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/events
     *
     * Query params: page, per_page/limit, status, search
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('per_page', $this->queryInt('limit', 20, 1, 100), 1, 100);
        $offset = ($page - 1) * $limit;

        $status = $this->query('status');
        $search = $this->query('search');

        $conditions = ['tenant_id = ?'];
        $params = [$tenantId];

        if ($status) {
            $conditions[] = 'status = ?';
            $params[] = $status;
        }

        if ($search) {
            $conditions[] = '(title LIKE ? OR description LIKE ?)';
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM events WHERE {$where}",
            $params
        )->cnt;

        $items = DB::select(
            "SELECT * FROM events WHERE {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        return $this->respondWithPaginatedCollection($items, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/events/{id}
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $event = DB::selectOne(
            'SELECT * FROM events WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($event === null) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }

        return $this->respondWithData($event);
    }

    /**
     * POST /api/v2/admin/events/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $affected = DB::update(
            'UPDATE events SET status = ? WHERE id = ? AND tenant_id = ?',
            ['active', $id, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'status' => 'active']);
    }

    /**
     * DELETE /api/v2/admin/events/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $event = EventService::getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }

        $deleted = EventService::delete($id, $adminId);

        if ($deleted) {
            return $this->respondWithData(['id' => $id, 'deleted' => true]);
        }

        return $this->respondWithError('DELETE_FAILED', 'Failed to delete event', null, 400);
    }

    /**
     * POST /api/v2/admin/events/{id}/cancel
     */
    public function cancel(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();

        $reason = $this->input('reason', 'Cancelled by admin');
        $cancelled = EventService::cancelEvent($id, $adminId, $reason);

        if ($cancelled) {
            return $this->respondWithData(['cancelled' => true, 'id' => $id]);
        }

        return $this->respondWithError('CANCEL_FAILED', 'Failed to cancel event', null, 400);
    }
}
