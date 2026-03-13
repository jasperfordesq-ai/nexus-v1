<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\EventService;

/**
 * Admin Events API Controller
 *
 * GET    /api/v2/admin/events              - List all events
 * GET    /api/v2/admin/events/{id}         - Event detail
 * DELETE /api/v2/admin/events/{id}         - Delete event
 * POST   /api/v2/admin/events/{id}/cancel  - Cancel event
 */
class AdminEventsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function index(): void
    {
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'search' => $this->query('search'),
            'page' => max(1, $this->queryInt('page', 1)),
            'limit' => min(200, max(1, $this->queryInt('limit', 50))),
        ];

        $result = EventService::getAll($filters);

        $items = $result['data'] ?? $result['items'] ?? $result;
        $total = $result['total'] ?? (is_array($items) ? count($items) : 0);
        $page = $filters['page'];
        $limit = $filters['limit'];

        if (is_array($items) && !isset($result['total'])) {
            $offset = ($page - 1) * $limit;
            $paged = array_slice($items, $offset, $limit);
            $this->respondWithPaginatedCollection($paged, count($items), $page, $limit);
        } else {
            $this->respondWithPaginatedCollection($items, $total, $page, $limit);
        }
    }

    public function show(int $id): void
    {
        $this->requireAdmin();

        $event = EventService::getById($id);

        if (!$event) {
            $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
            return;
        }

        $this->respondWithData($event);
    }

    public function destroy(int $id): void
    {
        $adminId = $this->requireAdmin();

        $event = EventService::getById($id);
        if (!$event) {
            $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
            return;
        }

        $deleted = EventService::delete($id, $adminId);

        if ($deleted) {
            $this->respondWithData(['deleted' => true, 'id' => $id]);
        } else {
            $this->respondWithError('DELETE_FAILED', 'Failed to delete event', null, 400);
            return;
        }
    }

    public function cancel(int $id): void
    {
        $adminId = $this->requireAdmin();

        $reason = $this->input('reason', 'Cancelled by admin');
        $cancelled = EventService::cancelEvent($id, $adminId, $reason);

        if ($cancelled) {
            $this->respondWithData(['cancelled' => true, 'id' => $id]);
        } else {
            $this->respondWithError('CANCEL_FAILED', 'Failed to cancel event', null, 400);
            return;
        }
    }
}
